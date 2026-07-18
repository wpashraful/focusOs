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

        return Inertia::render('Chat/Index', [
            'conversations'      => $conversations,
            'activeConversation' => $activeConversation,
            'messages'           => $messages,
            'projects'           => $projects,
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

        $conversation = Conversation::create([
            'user_id'    => $request->user()->id,
            'project_id' => $validated['project_id'] ?? null,
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
        $lastUserMessage = $conversation->messages()->where('role', 'user')->latest()->first();
        $userText = $lastUserMessage ? $lastUserMessage->content : '';

        // 1. Detect Intent using Hybrid Intent Router
        $routing = $intentRouter->route($userText);

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

        // 4. Normal streaming pipeline
        return new StreamedResponse(function () use ($conversation, $contextBuilder, $routing, $toolRegistry, $userText) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Disable FastCGI buffering in Nginx/Apache

            // Fetch last 10 messages + system prompt
            $projectContext = "";
            $project = $conversation->project;
            if ($project) {
                $projectContext = $contextBuilder->build($project, 2000, $userText);
            }

            $systemPrompt = "You are FocusOS AI Coach, a premium productivity coach helping the user stay on track.\n"
                          . "Keep your answers highly actionable, brief, and structured.\n\n"
                          . $projectContext;

            $dbMessages = $conversation->messages()
                ->latest()
                ->take(10)
                ->get()
                ->reverse();

            $modelName = 'gemini-3.5-flash';
            if ($project && $project->aiSetting) {
                $modelName = $project->aiSetting->model_name;
            }

            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:streamGenerateContent?key=" . env('GEMINI_API_KEY');

            $payload = [
                'contents' => [],
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]]
                ]
            ];

            $generationConfig = [];
            if ($project && $project->aiSetting) {
                if ($project->aiSetting->temperature !== null) {
                    $generationConfig['temperature'] = (float) $project->aiSetting->temperature;
                }
                if ($project->aiSetting->max_tokens !== null) {
                    $generationConfig['maxOutputTokens'] = (int) $project->aiSetting->max_tokens;
                }
            }
            if (!empty($generationConfig)) {
                $payload['generationConfig'] = $generationConfig;
            }

            // If the conversation already has a summary, prepend it
            if ($conversation->summary) {
                $payload['contents'][] = [
                    'role'  => 'user',
                    'parts' => [['text' => "Previous summary context: " . $conversation->summary]]
                ];
                $payload['contents'][] = [
                    'role'  => 'model',
                    'parts' => [['text' => "Understood. I will keep that in mind."]]
                ];
            }

            foreach ($dbMessages as $msg) {
                $payload['contents'][] = [
                    'role'  => ($msg->role === 'assistant') ? 'model' : 'user',
                    'parts' => [['text' => $msg->content]]
                ];
            }

            // Inject tools
            $tools = $toolRegistry->getDefinitions();
            if (!empty($tools)) {
                $payload['tools'] = [
                    ['functionDeclarations' => $tools]
                ];
            }

            // Run cURL stream
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);

            $fullResponseText = "";

            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$fullResponseText) {
                $lines = explode("\n", $data);
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if (empty($trimmed)) continue;

                    $trimmed = ltrim($trimmed, ',[');
                    $trimmed = rtrim($trimmed, ']');

                    $decoded = json_decode($trimmed, true);
                    $token = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;

                    if ($token) {
                        $fullResponseText .= $token;
                        echo "data: " . json_encode(['token' => $token]) . "\n\n";
                        ob_flush();
                        flush();
                    }
                }
                return strlen($data);
            });

            curl_exec($ch);
            curl_close($ch);

            // Save the complete streamed assistant response to DB
            if (!empty($fullResponseText)) {
                Message::create([
                    'conversation_id' => $conversation->id,
                    'role'            => 'assistant',
                    'content'         => $fullResponseText,
                    'is_streamed'     => true,
                ]);

                // Dispatch background memory extraction
                ExtractMemoryJob::dispatch($userText, $fullResponseText, $conversation->project);
            }

            echo "data: [DONE]\n\n";
            ob_flush();
            flush();
        });
    }
}
