<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAIChat;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\AIProviderInterface;
use App\Services\AI\ObservabilityLogger;
use App\Services\AI\ContextBuilder;
use App\Services\AI\HybridIntentRouter;
use App\Services\AI\FocusGuard;
use App\Services\AI\ToolRegistry;
use App\Jobs\ExtractMemoryJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    /**
     * Show chat page with conversation list and messages.
     */
    public function index(Request $request, $id = null)
    {
        $user = $request->user();

        $conversations = Conversation::where('user_id', $user->id)
            ->latest()
            ->get();

        $activeConversation = null;
        $messages = [];

        if ($id) {
            $activeConversation = Conversation::where('user_id', $user->id)
                ->where('id', $id)
                ->firstOrFail();
            $messages = $activeConversation->messages;
        } elseif ($conversations->isNotEmpty()) {
            $activeConversation = $conversations->first();
            $messages = $activeConversation->messages;
        }

        // Get active projects list for linking/context
        $projects = $user->workspaces()->with('projects')->get()
            ->pluck('projects')->flatten()
            ->where('status', 'active');

        $pendingActions = [];
        if ($activeConversation) {
            $pendingActions = \App\Models\PendingAction::where('conversation_id', $activeConversation->id)->get();
        }

        return Inertia::render('Chat/Index', [
            'conversations'      => $conversations,
            'activeConversation' => $activeConversation,
            'messages'           => $messages,
            'projects'           => $projects,
            'pendingActions'     => $pendingActions,
        ]);
    }

    /**
     * Create a new conversation.
     */
    public function start(Request $request)
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'exists:projects,id'],
            'title'      => ['nullable', 'string', 'max:100'],
        ]);

        $projectId = $validated['project_id'] ?? null;

        // Automatically default to the user's first active project if none was selected
        if (empty($projectId)) {
            $firstActiveProject = $request->user()->workspaces()->with('projects')->get()
                ->pluck('projects')->flatten()
                ->where('status', 'active')
                ->first();
            if ($firstActiveProject) {
                $projectId = $firstActiveProject->id;
            }
        }

        $conversation = Conversation::create([
            'user_id'    => $request->user()->id,
            'project_id' => $projectId,
            'title'      => $validated['title'] ?? 'New Chat Session',
        ]);

        return redirect()->route('chat.index', $conversation->id);
    }

    /**
     * Store user message and trigger assistant response.
     * Can run in background queue OR sync. We dispatch queue here.
     */
    public function store(Request $request, Conversation $conversation)
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        // Save user message
        $userMessage = Message::create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => $validated['content'],
        ]);

        // Dispatch background job to fetch Gemini response (non-streaming fallback)
        ProcessAIChat::dispatch($conversation, $validated['content']);

        return back();
    }

    /**
     * SSE Streaming Endpoint for Real-time tokens (Step 4.6)
     */
    public function stream(
        Conversation $conversation,
        AIProviderInterface $aiProvider,
        ObservabilityLogger $logger,
        ContextBuilder $contextBuilder,
        HybridIntentRouter $intentRouter,
        FocusGuard $focusGuard,
        ToolRegistry $toolRegistry
    ) {
        // Save and release session lock to prevent blocking concurrent requests
        session()->save();

        $lastUserMessage = $conversation->messages()->where('role', 'user')->reorder('created_at', 'desc')->first();
        $userText = $lastUserMessage ? $lastUserMessage->content : '';

        // 1. Detect Intent using Hybrid Intent Router
        $routing = $intentRouter->route($userText, $conversation);

        $progressAppliedNote = "";
        $pendingAction = null;

        // If the user reports goal progress, stage the update as a PendingAction instead of writing to DB
        if ($routing['intent'] === 'progress_report' && isset($routing['extracted']['value'])) {
            // Invalidate/expire any existing pending actions in this conversation to prevent conflicts
            \App\Models\PendingAction::where('conversation_id', $conversation->id)
                ->where('status', 'pending')
                ->update(['status' => 'expired']);

            // Create new pending action
            $pendingAction = \App\Models\PendingAction::create([
                'conversation_id' => $conversation->id,
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
            
            return new StreamedResponse(function () use ($conversation, $clarificationMsg) {
                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');
                header('Connection: keep-alive');
                header('X-Accel-Buffering: no');

                echo "data: " . json_encode(['token' => $clarificationMsg]) . "\n\n";
                echo "data: [DONE]\n\n";
                ob_flush();
                flush();

                Message::create([
                    'conversation_id' => $conversation->id,
                    'role'            => 'assistant',
                    'content'         => $clarificationMsg,
                ]);
            });
        }

        // 2. Intercept off-topic intents using FocusGuard
        if ($focusGuard->shouldRedirect($routing['intent'])) {
            $redirectMsg = $focusGuard->redirectResponse($conversation->project);

            return new StreamedResponse(function () use ($conversation, $redirectMsg) {
                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');
                header('Connection: keep-alive');
                header('X-Accel-Buffering: no');

                echo "data: " . json_encode(['token' => $redirectMsg]) . "\n\n";
                ob_flush();
                flush();

                Message::create([
                    'conversation_id' => $conversation->id,
                    'role'            => 'assistant',
                    'content'         => $redirectMsg,
                ]);

                echo "data: [DONE]\n\n";
                ob_flush();
                flush();
            });
        }

        // 3. Fast Intent Rule-based Tool Executions (Instant response, no LLM call)
        if (in_array($routing['intent'], ['done_report', 'delay_report', 'idea_capture'])) {
            $toolMap = [
                'done_report'  => ['name' => 'complete_task', 'args' => ['title' => $userText]],
                'delay_report' => ['name' => 'reschedule_routine', 'args' => ['delay_minutes' => $routing['extracted']['delay_minutes'] ?? 15]],
                'idea_capture' => ['name' => 'save_future_idea', 'args' => ['title' => $routing['extracted']['idea'] ?? $userText]],
            ];

            $toolDef = $toolMap[$routing['intent']];
            $outcome = $toolRegistry->execute($toolDef['name'], $toolDef['args'], $conversation->project);
            $outcomeMsg = $outcome['result'] ?? 'Action completed successfully.';

            return new StreamedResponse(function () use ($conversation, $outcomeMsg) {
                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');
                header('Connection: keep-alive');
                header('X-Accel-Buffering: no');

                echo "data: " . json_encode(['token' => "🛠️ Action Executed:\n" . $outcomeMsg]) . "\n\n";
                ob_flush();
                flush();

                Message::create([
                    'conversation_id' => $conversation->id,
                    'role'            => 'assistant',
                    'content'         => $outcomeMsg,
                ]);

                echo "data: [DONE]\n\n";
                ob_flush();
                flush();
            });
        }

        // 3.5 Pull updates from Google Sheets first if this is a status check
        if ($routing['intent'] === 'status_check' && $conversation->project) {
            try {
                app(\App\Services\GoogleSheetsService::class)->syncFromSheet($conversation->project);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to sync from Google Sheets: " . $e->getMessage());
            }
        }

        // 4. Normal streaming pipeline
        return new StreamedResponse(function () use ($conversation, $contextBuilder, $routing, $toolRegistry, $userText, $aiProvider, $progressAppliedNote, &$pendingAction) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Disable FastCGI buffering in Nginx/Apache

            // Fetch last 10 messages + system prompt
            $projectContext = "";
            $project = $conversation->project;
            if ($project) {
                $projectContext = $contextBuilder->build($project, 2000, $userText) . $progressAppliedNote;
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

            $dbMessages = $conversation->messages()
                ->reorder('created_at', 'desc')
                ->take(10)
                ->get()
                ->reverse();

            $modelName = 'gemini-2.5-flash';
            $temperature = 0.7;
            $maxTokens = 2048;

            if ($project && $project->aiSetting) {
                $modelName = $project->aiSetting->model_name;
                if ($project->aiSetting->temperature !== null) {
                    $temperature = (float) $project->aiSetting->temperature;
                }
                if ($project->aiSetting->max_tokens !== null) {
                    $maxTokens = (int) $project->aiSetting->max_tokens;
                }
            }

            $messages = [];
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];

            // If the conversation already has a summary, prepend it
            if ($conversation->summary) {
                $messages[] = [
                    'role'    => 'user',
                    'content' => "Previous summary context: " . $conversation->summary
                ];
                $messages[] = [
                    'role'    => 'model',
                    'content' => "Understood. I will keep that in mind."
                ];
            }

            foreach ($dbMessages as $msg) {
                $messages[] = [
                    'role'    => ($msg->role === 'assistant') ? 'model' : 'user',
                    'content' => $msg->content
                ];
            }

            // Inject tools
            $tools = $toolRegistry->getDefinitions();

            $fullResponseText = "";

             $options = [
                 'model'          => $modelName,
                 'temperature'    => $temperature,
                 'max_tokens'     => $maxTokens,
                 'tools'          => $tools,
                 'project'        => $project,
                 'token_callback' => function ($token) use (&$fullResponseText) {
                     $fullResponseText .= $token;
                     echo "data: " . json_encode(['token' => $token]) . "\n\n";
                     ob_flush();
                     flush();
                 }
             ];

            // Stream response using provider and catch any failures to display the exact cause to the user
             try {
                 $aiProvider->stream($messages, $options);

                 // Save the complete streamed assistant response to DB
                 if (!empty($fullResponseText)) {
                     $msg = Message::create([
                         'conversation_id' => $conversation->id,
                         'role'            => 'assistant',
                         'content'         => $fullResponseText,
                         'is_streamed'     => true,
                     ]);

                     if (isset($pendingAction)) {
                         $pendingAction->update(['message_id' => $msg->id]);
                     }

                     // Dispatch background memory extraction (sync to ensure it runs without queue worker)
                     ExtractMemoryJob::dispatchSync($userText, $fullResponseText, $conversation->project);
                 } else {
                     // If stream failed (empty response), create an error message to display in frontend
                     $errorMsg = "⚠️ FocusOS Coach was unable to connect to the AI provider. This is usually due to OpenRouter's rate limits on free accounts. Please verify your OpenRouter key, try again in a few seconds, or switch to Gemini in your project settings.";
                     
                     Message::create([
                         'conversation_id' => $conversation->id,
                         'role'            => 'assistant',
                         'content'         => $errorMsg,
                     ]);

                     // Echo the error token so the client stream displays it immediately too
                     echo "data: " . json_encode(['token' => $errorMsg]) . "\n\n";
                     ob_flush();
                     flush();
                 }
             } catch (\Exception $e) {
                 $causeMsg = "⚠️ FocusOS Coach Error: " . $e->getMessage();
                 
                 Message::create([
                     'conversation_id' => $conversation->id,
                     'role'            => 'assistant',
                     'content'         => $causeMsg,
                 ]);

                 // Stream the exact exception cause to the browser
                 echo "data: " . json_encode(['token' => $causeMsg]) . "\n\n";
                 ob_flush();
                 flush();
             }

             echo "data: [DONE]\n\n";
            ob_flush();
            flush();
        });
    }

    /**
     * Confirm a pending action and update the database.
     */
    public function confirmAction(\App\Models\PendingAction $action)
    {
        if ($action->status !== 'pending') {
            return back()->with('error', 'Action is no longer pending.');
        }

        if ($action->isExpired()) {
            $action->update(['status' => 'expired']);
            return back()->with('error', 'Action has expired.');
        }

        // Execute database tool
        $toolRegistry = app(\App\Services\AI\ToolRegistry::class);
        $payload = $action->payload;
        $payload['conversation_id'] = $action->conversation_id;
        $payload['router'] = 'llm_extractor';
        $payload['confidence'] = 1.0;

        $outcome = $toolRegistry->execute('update_goal_progress', $payload, $action->conversation->project);

        // Check if there was a validation error (e.g. Entity mismatch or value too high)
        if (isset($outcome['result']) && str_starts_with($outcome['result'], 'Validation Error:')) {
            // Keep status pending but display error as a reply
            Message::create([
                'conversation_id' => $action->conversation_id,
                'role'            => 'assistant',
                'content'         => "⚠️ **Validation Failed:** " . $outcome['result'],
            ]);
            return back()->with('error', $outcome['result']);
        }

        $action->update(['status' => 'confirmed']);

        // Create confirmation message
        Message::create([
            'conversation_id' => $action->conversation_id,
            'role'            => 'assistant',
            'content'         => "✅ **Confirmed!** I have applied the goal update: " . ($outcome['result'] ?? 'Goal updated successfully.'),
        ]);

        return back();
    }

    /**
     * Cancel a pending action.
     */
    public function cancelAction(\App\Models\PendingAction $action)
    {
        if ($action->status !== 'pending') {
            return back()->with('error', 'Action is no longer pending.');
        }

        $action->update(['status' => 'cancelled']);

        // Create cancel message
        Message::create([
            'conversation_id' => $action->conversation_id,
            'role'            => 'assistant',
            'content'         => "❌ **Cancelled.** The goal update has been discarded.",
        ]);

        return back();
    }

    /**
     * Undo a confirmed action.
     */
    public function undoAction(\App\Models\PendingAction $action)
    {
        if ($action->status !== 'confirmed') {
            return back()->with('error', 'Action was not confirmed.');
        }

        if (now()->diffInMinutes($action->updated_at) > 5) {
            return back()->with('error', 'Undo window has expired.');
        }

        // Retrieve audit log to find previous value
        $audit = \App\Models\ProjectStateAudit::where('project_id', $action->conversation->project_id)
            ->where('conversation_id', $action->conversation_id)
            ->where('operation', '!=', 'undo')
            ->latest()
            ->first();

        if ($audit) {
            $project = $action->conversation->project;
            $goal = $project->goals()->where('title', $audit->goal_title)->first();
            if ($goal) {
                $oldVal = $goal->current_value;
                $goal->current_value = $audit->previous_value;
                $goal->save();

                $action->update(['status' => 'undone']);

                // Log the undo in audits
                \App\Models\ProjectStateAudit::create([
                    'project_id'      => $project->id,
                    'conversation_id' => $action->conversation_id,
                    'goal_title'      => $goal->title,
                    'operation'       => 'undo',
                    'value'           => abs($oldVal - $goal->current_value),
                    'previous_value'  => $oldVal,
                    'new_value'       => $goal->current_value,
                    'entity'          => $audit->entity,
                    'router'          => 'undo_handler',
                    'confidence'      => 1.0,
                ]);

                // Create undo message
                Message::create([
                    'conversation_id' => $action->conversation_id,
                    'role'            => 'assistant',
                    'content'         => "↩️ **Undone.** Reverted the goal value from {$oldVal} back to {$goal->current_value}.",
                ]);

                return back();
            }
        }

        return back()->with('error', 'Could not find audit logs to undo.');
    }
}
