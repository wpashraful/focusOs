<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\AIProviderInterface;
use App\Services\AI\ObservabilityLogger;
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
    public function handle(AIProviderInterface $aiProvider, ObservabilityLogger $logger): void
    {
        // 1. Fetch system prompt context
        $systemPrompt = $this->buildSystemPrompt();

        // 2. Fetch last 10 messages from DB
        $dbMessages = $this->conversation->messages()
            ->latest()
            ->take(10)
            ->get()
            ->reverse();

        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];

        foreach ($dbMessages as $msg) {
            $messages[] = [
                'role'    => $msg->role,
                'content' => $msg->content,
            ];
        }

        // If the conversation already has a summary, we can prepend it to the context
        if ($this->conversation->summary) {
            $messages[] = [
                'role'    => 'system',
                'content' => "Summary of previous conversation context: " . $this->conversation->summary
            ];
        }

        // 3. Call AI
        try {
            $response = $aiProvider->chat($messages, [
                'log_callback' => function ($logData) use ($logger) {
                    $logData['project_id'] = $this->conversation->project_id;
                    $logData['action'] = 'chat';
                    $logger->log($logData);
                }
            ]);

            // 4. Save response to database
            Message::create([
                'conversation_id'  => $this->conversation->id,
                'role'             => 'assistant',
                'content'          => $response['text'],
                'tokens_estimated' => $response['completion_tokens'],
            ]);

        } catch (\Exception $e) {
            Log::error("Failed processing AI Chat: " . $e->getMessage());

            Message::create([
                'conversation_id' => $this->conversation->id,
                'role'            => 'assistant',
                'content'         => "Sorry, I encountered an error while processing your request. Please try again.",
            ]);
        }
    }

    /**
     * Build basic system prompt from project context (Step 4.8)
     */
    protected function buildSystemPrompt(): string
    {
        $prompt = "You are FocusOS AI Coach, a premium productivity coach helping the user stay on track.\n"
                . "Keep your answers highly actionable, brief, and structured.\n";

        $project = $this->conversation->project;
        if ($project) {
            $prompt .= "\nYou are currently assisting in the project: \"{$project->name}\".\n";
            if ($project->current_phase_name) {
                $prompt .= "Current Project Phase: {$project->current_phase_name}\n";
            }
            if ($project->current_phase_goal) {
                $prompt .= "Phase Goal: {$project->current_phase_goal}\n";
            }
        }

        return $prompt;
    }
}
