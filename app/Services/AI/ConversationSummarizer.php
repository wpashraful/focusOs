<?php

namespace App\Services\AI;

use App\Models\Conversation;

class ConversationSummarizer
{
    protected AIProviderInterface $aiProvider;
    protected TokenBudget $budget;

    public function __construct(AIProviderInterface $aiProvider, TokenBudget $budget)
    {
        $this->aiProvider = $aiProvider;
        $this->budget = $budget;
    }

    /**
     * Determine if a conversation is due for a new summary.
     */
    public function shouldSummarize(Conversation $conversation): bool
    {
        $messageCount = $conversation->messages()->count();
        if ($messageCount < 20) {
            return false;
        }

        // Estimate total token count in DB messages
        $totalEstimatedTokens = $conversation->messages()->sum('tokens_estimated');

        // Summarize if token count exceeds 8000
        return $totalEstimatedTokens >= 8000;
    }

    /**
     * Summarize the conversation history and update the conversation record.
     */
    public function summarize(Conversation $conversation): string
    {
        $messages = $conversation->messages()->get();

        $historyText = "";
        foreach ($messages as $msg) {
            $historyText .= "[" . ucfirst($msg->role) . "]: " . $msg->content . "\n";
        }

        $prompt = "You are a summary agent. Write a concise, bullet-pointed summary of the following conversation history.\n"
                . "Focus on: user's main goals, active work discussions, routine delays, and decisions made.\n"
                . "Keep the summary under 300 words.\n\n"
                . "Conversation History:\n"
                . $historyText . "\n\n"
                . "Summary:";

        $response = $this->aiProvider->chat([
            ['role' => 'user', 'content' => $prompt]
        ], [
            'temperature' => 0.3,
            'max_tokens' => 500
        ]);

        $summary = $response['text'];

        // Save inline to conversation
        $conversation->update(['summary' => $summary]);

        return $summary;
    }
}
