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
        ToolRegistry $toolRegistry
    ): void {
        // 1. Detect Intent using Hybrid Intent Router
        $routing = $intentRouter->route($this->userMessageText);

        // 2. Intercept off-topic intents using FocusGuard
        if ($focusGuard->shouldRedirect($routing['intent'])) {
            $redirectMsg = $focusGuard->redirectResponse($this->conversation->project);

            Message::create([
                'conversation_id' => $this->conversation->id,
                'role'            => 'assistant',
                'content'         => $redirectMsg,
            ]);

            if ($summarizer->shouldSummarize($this->conversation)) {
                SummarizeConversationJob::dispatch($this->conversation);
            }
            return;
        }

        // 3. Build system prompt from active project context
        $projectContext = "";
        if ($this->conversation->project) {
            $projectContext = $contextBuilder->build($this->conversation->project, 2000);
        }

        $systemPrompt = "You are FocusOS AI Coach, a premium productivity coach helping the user stay on track.\n"
                      . "Keep your answers highly actionable, brief, and structured.\n\n"
                      . $projectContext;

        // 4. Fetch last 10 messages from DB
        $dbMessages = $this->conversation->messages()
            ->latest()
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
            $response = $aiProvider->chat($messages, [
                'tools'        => $tools,
                'project'      => $this->conversation->project,
                'log_callback' => function ($logData) use ($logger) {
                    $logData['project_id'] = $this->conversation->project_id;
                    $logData['action'] = 'chat';
                    $logger->log($logData);
                }
            ]);

            // 7. Save response to database
            Message::create([
                'conversation_id'  => $this->conversation->id,
                'role'             => 'assistant',
                'content'          => $response['text'],
                'tokens_estimated' => $response['completion_tokens'],
            ]);

            // Dispatch background memory extraction
            ExtractMemoryJob::dispatch($this->userMessageText, $response['text'], $this->conversation->project);

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
