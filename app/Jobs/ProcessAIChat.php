<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\AIProviderInterface;
use App\Services\AI\ContextBuilder;
use App\Services\AI\ConversationSummarizer;
use App\Services\AI\FocusGuard;
use App\Services\AI\HybridIntentRouter;
use App\Services\AI\ObservabilityLogger;
use App\Services\AI\ToolRegistry;
use App\Jobs\ExtractMemoryJob;
use App\Services\Telegram\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAIChat implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Conversation $conversation;
    protected string $userMessageText;

    /**
     * Create a new job instance.
     */
    public function __construct(Conversation $conversation, string $userMessageText)
    {
        $this->conversation = $conversation;
        $this->userMessageText = $userMessageText;
    }

    /**
     * Execute the job.
     */
    public function handle(
        AIProviderInterface $aiProvider,
        ObservabilityLogger $logger,
        ContextBuilder $contextBuilder,
        HybridIntentRouter $intentRouter,
        FocusGuard $focusGuard,
        ConversationSummarizer $summarizer,
        ToolRegistry $toolRegistry,
        TelegramService $telegramService
    ): void {
        // 1. Detect Intent using Hybrid Intent Router
        $routing = $intentRouter->route($this->userMessageText, $this->conversation);

        $progressAppliedNote = "";
        $pendingAction = null;

        // If the user reports goal progress, stage the update as a PendingAction instead of writing to DB
        if ($routing['intent'] === 'progress_report' && isset($routing['extracted']['value'])) {
            // Invalidate/expire any existing pending actions in this conversation to prevent conflicts
            \App\Models\PendingAction::where('conversation_id', $this->conversation->id)
                ->where('status', 'pending')
                ->update(['status' => 'expired']);

            // Create new pending action
            $pendingAction = \App\Models\PendingAction::create([
                'conversation_id' => $this->conversation->id,
                'action_type'     => 'goal_update',
                'payload'         => [
                    'value'      => $routing['extracted']['value'],
                    'operation'  => $routing['extracted']['operation'] ?? 'increment',
                    'entity'     => $routing['extracted']['entity'] ?? 'leads',
                    'goal_title' => $routing['extracted']['goal_title'] ?? null,
                ],
                'status'          => 'pending',
                'expires_at'      => now()->addMinutes(15),
            ]);

            $progressAppliedNote = "\n[SYSTEM NOTE: The user's goal update of {$routing['extracted']['value']} has NOT been applied to the database yet. It has been staged as a pending action (ID: {$pendingAction->id}) that requires confirmation. Summarize the change and ask the user to confirm it. State clearly that they can use the buttons below. Do not execute it yourself.]\n";
        }

        // 1.5 Intercept need_clarification intent
        if ($routing['intent'] === 'need_clarification') {
            $clarificationMsg = "🤔 FocusOS Coach: I think you are reporting progress, but I'm not entirely sure about the details (value or entity). Could you please clarify exactly how many leads/units you have collected, added, or set?";
            
            Message::create([
                'conversation_id' => $this->conversation->id,
                'role'            => 'assistant',
                'content'         => $clarificationMsg,
            ]);

            // Reply to Telegram if chat ID exists
            $telegramChatId = cache()->pull("conversation_{$this->conversation->id}_telegram_chat_id");
            if ($telegramChatId) {
                $telegramService->sendMessage($telegramChatId, $clarificationMsg);
            }
            return;
        }

        // 2. Intercept off-topic intents using FocusGuard
        if ($focusGuard->shouldRedirect($routing['intent'])) {
            $redirectMsg = $focusGuard->redirectResponse($this->conversation->project);

            Message::create([
                'conversation_id' => $this->conversation->id,
                'role'            => 'assistant',
                'content'         => $redirectMsg,
            ]);

            // Reply to Telegram if chat ID exists
            $telegramChatId = cache()->pull("conversation_{$this->conversation->id}_telegram_chat_id");
            if ($telegramChatId) {
                $telegramService->sendMessage($telegramChatId, $redirectMsg);
            }

            if ($summarizer->shouldSummarize($this->conversation)) {
                SummarizeConversationJob::dispatch($this->conversation);
            }
            return;
        }

        // 3. Build system prompt from active project context
        $projectContext = "";
        if ($this->conversation->project) {
            $projectContext = $contextBuilder->build($this->conversation->project, 2000, $this->userMessageText) . $progressAppliedNote;
        }

        $systemPrompt = "You are FocusOS AI Coach, a premium productivity coach helping the user stay on track.\n"
                      . "Keep your answers highly actionable, brief, and structured.\n\n"
                      . "CRITICAL: Always treat the Active Goals values from the database context as the single source of truth for numeric progress. If long-term memories contradict the database goal count, always trust and output the database goal count, not the memory value.\n\n"
                      . "=== PROJECT STATE ENGINE ===\n"
                      . "You have access to tools to update project state variables (like current phase, active goals, completed items, etc.) directly in the database. Use them whenever the user reports progress or phase updates.\n"
                      . "If you run any state engine tools or if the project has active goals/phases, output a clean, formatted Markdown box summarizing the updated state at the very end of your message using this exact template:\n\n"
                      . "📂 **Project State Dashboard**\n"
                      . "- **Project:** {project_name}\n"
                      . "- **Phase:** {phase_name} ({phase_goal})\n"
                      . "- **Active Goal:** {goal_title} ({current_value} / {target_value} {unit} completed)\n"
                      . "- **Remaining:** {remaining_value} {unit}\n"
                      . "- **Next Action:** {next_action_title}\n\n"
                      . "=============================\n\n"
                      . $projectContext;

        // 4. Fetch last 10 messages from DB
        $dbMessages = $this->conversation->messages()
            ->reorder('created_at', 'desc')
            ->take(10)
            ->get()
            ->reverse();

        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];

        // If the conversation already has a summary, prepend it
        if ($this->conversation->summary) {
            $messages[] = [
                'role'    => 'system',
                'content' => "Summary of previous conversation context: " . $this->conversation->summary
            ];
        }

        foreach ($dbMessages as $msg) {
            $messages[] = [
                'role'    => $msg->role,
                'content' => $msg->content,
            ];
        }

        // 5. Gather Tool Definitions
        $tools = $toolRegistry->getDefinitions();

        // 6. Call AI with tools injection
        try {
            $options = [
                'tools'        => $tools,
                'project'      => $this->conversation->project,
                'log_callback' => function ($logData) use ($logger) {
                    $logData['project_id'] = $this->conversation->project_id;
                    $logData['action'] = 'chat';
                    $logger->log($logData);
                }
            ];

            $project = $this->conversation->project;
            if ($project && $project->aiSetting) {
                $options['model'] = $project->aiSetting->model_name;
                $options['temperature'] = (float) $project->aiSetting->temperature;
                $options['max_tokens'] = (int) $project->aiSetting->max_tokens;
            }

            $response = $aiProvider->chat($messages, $options);

            // 7. Save response to database
            $msg = Message::create([
                'conversation_id'  => $this->conversation->id,
                'role'             => 'assistant',
                'content'          => $response['text'],
                'tokens_estimated' => $response['completion_tokens'],
            ]);

            if (isset($pendingAction)) {
                $pendingAction->update(['message_id' => $msg->id]);
            }

            // Reply to Telegram if chat ID exists
            $telegramChatId = cache()->pull("conversation_{$this->conversation->id}_telegram_chat_id");
            if ($telegramChatId) {
                $telegramService->sendMessage($telegramChatId, $response['text']);
            }

            // Dispatch background memory extraction (sync to ensure it runs without queue worker)
            ExtractMemoryJob::dispatchSync($this->userMessageText, $response['text'], $this->conversation->project);

            // 8. Dispatch background summarization check
            if ($summarizer->shouldSummarize($this->conversation)) {
                SummarizeConversationJob::dispatch($this->conversation);
            }

        } catch (\Exception $e) {
            Log::error("Failed processing AI Chat: " . $e->getMessage());

            Message::create([
                'conversation_id' => $this->conversation->id,
                'role'            => 'assistant',
                'content'         => "Sorry, I encountered an error while processing your request. Please try again.",
            ]);
        }
    }
}
