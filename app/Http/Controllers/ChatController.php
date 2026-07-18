<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAIChat;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\AIProviderInterface;
use App\Services\AI\ObservabilityLogger;
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

        return redirect()->route('chat.show', $conversation->id);
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
    public function stream(Conversation $conversation, AIProviderInterface $aiProvider, ObservabilityLogger $logger)
    {
        return new StreamedResponse(function () use ($conversation, $aiProvider, $logger) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Disable FastCGI buffering in Nginx/Apache

            // Fetch last 10 messages + system prompt
            $systemPrompt = "You are FocusOS AI Coach. Assist the user with their current task/goals.";
            $project = $conversation->project;
            if ($project) {
                $systemPrompt .= "\nProject Context: \"{$project->name}\"\n";
                if ($project->current_phase_name) {
                    $systemPrompt .= "Phase: {$project->current_phase_name}. Goal: {$project->current_phase_goal}";
                }
            }

            $dbMessages = $conversation->messages()
                ->latest()
                ->take(10)
                ->get()
                ->reverse();

            $messages = [];
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
            foreach ($dbMessages as $msg) {
                $messages[] = ['role' => $msg->role, 'content' => $msg->content];
            }

            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:streamGenerateContent?key=" . env('GEMINI_API_KEY');

            $payload = [
                'contents' => [],
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]]
                ]
            ];

            foreach ($dbMessages as $msg) {
                $payload['contents'][] = [
                    'role'  => ($msg->role === 'assistant') ? 'model' : 'user',
                    'parts' => [['text' => $msg->content]]
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
                // Parse Gemini stream JSON chunks
                // Format: [{"candidates": [{"content": {"parts": [{"text": "chunk"}]}}]}]
                // Since curl yields raw chunks, we split/decode them
                $lines = explode("\n", $data);
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if (empty($trimmed)) continue;

                    // Remove leading comma or bracket if it arrives that way
                    $trimmed = ltrim($trimmed, ',[');
                    $trimmed = rtrim($trimmed, ']');

                    $decoded = json_decode($trimmed, true);
                    $token = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;

                    if ($token) {
                        $fullResponseText .= $token;
                        // Format as SSE
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
            }

            echo "data: [DONE]\n\n";
            ob_flush();
            flush();
        });
    }
}
