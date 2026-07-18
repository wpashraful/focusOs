<?php

namespace App\Services\AI;

class IntentRuleEngine
{
    /**
     * Parse message text against local regular expressions.
     *
     * @param  string  $message
     * @return array|null       [intent, extracted] or null
     */
    public function detect(string $message): ?array
    {
        $text = trim(strtolower($message));

        // 1. "done" pattern
        if ($text === 'done' || preg_match('/^(i\'ve\s+)?completed\s+my\s+task/i', $text)) {
            return [
                'intent'    => 'done_report',
                'extracted' => []
            ];
        }

        // 2. "what next" / "now what" pattern
        if (preg_match('/^(now\s+what|what\'s?\s+next|next|what\s+should\s+i\s+do)/i', $text)) {
            return [
                'intent'    => 'status_check',
                'extracted' => []
            ];
        }

        // 3. "i'm late" / "delay" pattern
        if (preg_match('/^(i\'m\s+late|delay\s+(\d+)\s*m)/i', $text, $matches)) {
            $delay = isset($matches[2]) ? (int)$matches[2] : 15; // default 15 min delay
            return [
                'intent'    => 'delay_report',
                'extracted' => ['delay_minutes' => $delay]
            ];
        }

        // 4. "/idea" pattern
        if (str_starts_with($text, '/idea')) {
            $ideaText = trim(substr($message, 5));
            return [
                'intent'    => 'idea_capture',
                'extracted' => ['idea' => $ideaText]
            ];
        }

        return null;
    }
}
