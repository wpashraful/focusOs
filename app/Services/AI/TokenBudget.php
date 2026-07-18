<?php

namespace App\Services\AI;

class TokenBudget
{
    /**
     * Estimate token count of a given text block.
     * Rule of thumb: word count * 1.3
     */
    public function estimate(string $text): int
    {
        $wordCount = str_word_count($text);
        if ($wordCount === 0) {
            // fallback for character-dense non-space text (e.g. log outputs)
            return (int) ceil(mb_strlen($text) / 4);
        }
        return (int) ceil($wordCount * 1.3);
    }

    /**
     * Determine if a text fits within the given token budget.
     */
    public function fits(string $text, int $budget): bool
    {
        return $this->estimate($text) <= $budget;
    }

    /**
     * Truncate the text block to fit within the token budget.
     */
    public function trim(string $text, int $budget): string
    {
        if ($this->fits($text, $budget)) {
            return $text;
        }

        // Binary search to find optimal character length that fits within budget
        $low = 0;
        $high = mb_strlen($text);
        $result = "";

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            $sub = mb_substr($text, 0, $mid);

            if ($this->fits($sub, $budget)) {
                $result = $sub;
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return $result . " [TRUNCATED DUE TO BUDGET LIMIT]";
    }
}
