<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAIChat;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Telegram\TelegramService;
use App\Services\AI\ToolRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    protected TelegramService $telegram;

    public function __construct(TelegramService $telegram)
    {
        $this->telegram = $telegram;
    }

    /**
     * Handle incoming Telegram webhook updates.
     */
    public function handle(Request $request)
    {
        // Simple token query verification (matching token in webhook URL)
        $token = $request->query('token');
        if ($token && $token !== env('TELEGRAM_BOT_TOKEN')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $update = $request->all();

        // Check if it's a text message
        $message = $update['message'] ?? null;
        if (!$message || empty($message['text'])) {
            return response()->json(['status' => 'ignored']);
        }

        $chatId = $message['chat']['id'];
        $telegramId = $message['from']['id'];
        $text = trim($message['text']);

        // Find linked user
        $user = User::where('telegram_id', $telegramId)->first();

        if (!$user) {
            $this->telegram->sendMessage($chatId, "⚠️ *Account Not Linked*\nYour Telegram account is not linked to any FocusOS user. Please log in to the web app, go to Settings → Telegram, and link your account.");
            return response()->json(['status' => 'unlinked']);
        }

        // Handle Telegram Commands (Step 9.4)
        if (str_starts_with($text, '/')) {
            $this->handleCommand($text, $chatId, $user);
            return response()->json(['status' => 'command_handled']);
        }

        // Get or Create Active Project Conversation
        $project = $user->workspaces()->with('projects')->get()
            ->pluck('projects')->flatten()
            ->where('status', 'active')
            ->first();

        $conversation = Conversation::firstOrCreate(
            [
                'user_id'    => $user->id,
                'project_id' => $project?->id,
            ],
            [
                'title' => 'Telegram Coach Session',
            ]
        );

        // Store User message
        $userMsg = Message::create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => $text,
        ]);

        // Dispatch background processing, tag with chat_id for telegram reply
        ProcessAIChat::dispatch($conversation, $text)
            ->delay(now())
            ->onQueue('default');

        // Store chat_id temporary context inside session/cache so job knows where to reply
        cache()->put("conversation_{$conversation->id}_telegram_chat_id", $chatId, 3600);

        return response()->json(['status' => 'queued']);
    }

    /**
     * Parse and respond to slash commands (Step 9.4)
     */
    protected function handleCommand(string $text, int $chatId, User $user): void
    {
        $parts = explode(' ', $text, 2);
        $command = strtolower($parts[0]);
        $argument = isset($parts[1]) ? trim($parts[1]) : '';

        $project = $user->workspaces()->with('projects')->get()
            ->pluck('projects')->flatten()
            ->where('status', 'active')
            ->first();

        switch ($command) {
            case '/tasks':
                if (!$project) {
                    $this->telegram->sendMessage($chatId, "📁 No active project found.");
                    return;
                }
                $tasks = $project->tasks()->today()->pending()->get();
                if ($tasks->isEmpty()) {
                    $this->telegram->sendMessage($chatId, "🎉 *All tasks done!* Nothing pending for today.");
                } else {
                    $reply = "⏳ *Today's Pending Tasks:*\n";
                    foreach ($tasks as $t) {
                        $reply .= "- {$t->title} (Priority: {$t->priority})\n";
                    }
                    $this->telegram->sendMessage($chatId, $reply);
                }
                break;

            case '/status':
                if (!$project) {
                    $this->telegram->sendMessage($chatId, "📁 No active project found.");
                    return;
                }
                $completed = $project->tasks()->today()->where('status', 'done')->count();
                $pending = $project->tasks()->today()->pending()->count();
                $metrics = $project->dailyTargets()->with('todayLog')->get();

                $reply = "📊 *Daily Focus Summary:*\n"
                       . "- Tasks Completed today: *{$completed}*\n"
                       . "- Tasks Pending today: *{$pending}*\n\n";

                if ($metrics->isNotEmpty()) {
                    $reply .= "📈 *Daily Metrics:*\n";
                    foreach ($metrics as $m) {
                        $achieved = $m->todayLog?->achieved_count ?? 0;
                        $reply .= "- {$m->label}: {$achieved} / {$m->target_count}\n";
                    }
                }
                $this->telegram->sendMessage($chatId, $reply);
                break;

            case '/done':
                if (!$project) {
                    $this->telegram->sendMessage($chatId, "📁 No active project found.");
                    return;
                }
                if (empty($argument)) {
                    $this->telegram->sendMessage($chatId, "⚠️ Please provide a task title. Format: `/done Task Title`");
                    return;
                }

                $registry = app(ToolRegistry::class);
                $res = $registry->execute('complete_task', ['title' => $argument], $project);
                $this->telegram->sendMessage($chatId, "🛠️ *" . ($res['result'] ?? 'Task status updated.') . "*");
                break;

            case '/idea':
                if (empty($argument)) {
                    $this->telegram->sendMessage($chatId, "⚠️ Please specify your idea. Format: `/idea Launch mobile app`");
                    return;
                }

                $registry = app(ToolRegistry::class);
                $res = $registry->execute('save_future_idea', ['title' => $argument], $project);
                $this->telegram->sendMessage($chatId, "💡 *" . ($res['result'] ?? 'Idea saved.') . "*");
                break;

            default:
                $this->telegram->sendMessage($chatId, "❓ *Unknown Command*\nAvailable commands:\n/tasks - Pending tasks\n/status - Focus stats\n/done {task} - Mark task done\n/idea {text} - Save future idea");
                break;
        }
    }
}
