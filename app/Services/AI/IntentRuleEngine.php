<?php

namespace App\Services\AI;

class IntentRuleEngine
{
    protected TextNormalizer $normalizer;

    public function __construct(TextNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

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

        // 5. Greeting patterns
        if (preg_match('/^(hi|hello|hey|greetings|yo|howdy)(\s+coach)?$/i', $text)) {
            return [
                'intent'    => 'greeting',
                'extracted' => []
            ];
        }

        // 6. Progress report patterns
        $normalizedText = $this->normalizer->normalize($message);
        if (preg_match('/(\d+)\s*(leads?|entries?|emails?|connections?|logs?|audits?|videos?|more|done)/i', $normalizedText, $matches) ||
            preg_match('/(collect(?:ed)?|add(?:ed)?|log(?:ged)?|done|sent|get|got(?:ten)?|make|made|scrape(?:d)?|find|found|gather(?:ed)?|source(?:d)?|input(?:ted)?)\s*(?:(?:another|about|around|approx|some|a|an|over|exactly)\s+)?(\d+)/i', $normalizedText, $matches)) {
            
            $value = 0;
            $entityCandidate = 'leads';

            if (is_numeric($matches[1])) {
                $value = (int)$matches[1];
                $entityCandidate = strtolower($matches[2] ?? 'leads');
            } else {
                $value = (int)$matches[2];
                if (preg_match('/(?:leads?|entries?|emails?|connections?|logs?|audits?|videos?)/i', $normalizedText, $entMatches)) {
                    $entityCandidate = strtolower($entMatches[0]);
                }
            }

            // Only set entity if it matches a concrete tracking unit, otherwise default to null
            $concreteUnits = ['lead', 'leads', 'email', 'emails', 'video', 'videos', 'audit', 'audits', 'connection', 'connections', 'log', 'logs'];
            $entity = in_array($entityCandidate, $concreteUnits) ? $entityCandidate : null;

            // Detect operations
            $operation = 'increment';
            if (preg_match('/(remove|delete|minus|sub|decrement)/i', $normalizedText)) {
                $operation = 'decrement';
            } elseif (preg_match('/(now have|we have|now is|now total)/i', $normalizedText)) {
                $operation = 'set_total';
            }

            return [
                'intent'    => 'progress_report',
                'extracted' => [
                    'value'     => $value,
                    'operation' => $operation,
                    'entity'    => $entity
                ]
            ];
        }

        return null;
    }
}
