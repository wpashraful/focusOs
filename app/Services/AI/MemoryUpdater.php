<?php

namespace App\Services\AI;

use App\Models\Memory;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MemoryUpdater
{
    protected AIProviderInterface $aiProvider;

    public function __construct(AIProviderInterface $aiProvider)
    {
        $this->aiProvider = $aiProvider;
    }

    /**
     * Scan conversation turn, extract memory candidates, and save them.
     */
    public function process(string $userMsg, string $assistantReply, ?Project $project = null): void
    {
        try {
            $prompt = "You are a memory extractor. Analyze the conversation turn below and extract any new facts, user state details, or long-term preferences that should be remembered.\n"
                    . "Format the output as a valid JSON object matching this schema:\n"
                    . "{\n"
                    . "  \"memories\": [\n"
                    . "    {\n"
                    . "      \"key\": \"string (short identifier e.g. energy_level, preferred_language)\",\n"
                    . "      \"value\": \"string (the detail to remember)\",\n"
                    . "      \"importance_score\": number (0.0 to 1.0 rating)\n"
                    . "    }\n"
                    . "  ]\n"
                    . "}\n\n"
                    . "Conversation:\n"
                    . "[User]: \"{$userMsg}\"\n"
                    . "[Assistant]: \"{$assistantReply}\"\n\n"
                    . "JSON Output:";

            $options = [
                'project'     => $project,
                'temperature' => 0.1,
                'max_tokens'  => 500,
            ];

            if ($project && $project->aiSetting) {
                $options['model'] = $project->aiSetting->model_name;
            }

            $res = $this->aiProvider->chat([
                ['role' => 'user', 'content' => $prompt]
            ], $options);

            // Simple cleaning of JSON fences if LLM returns them
            $text = trim($res['text']);
            $text = preg_replace('/^```json\s*/i', '', $text);
            $text = preg_replace('/```$/', '', $text);

            $data = json_decode($text, true);

            if (empty($data['memories'])) {
                return;
            }

            foreach ($data['memories'] as $candidate) {
                if (empty($candidate['key']) || empty($candidate['value'])) {
                    continue;
                }

                $score = floatval($candidate['importance_score'] ?? 0.5);

                // Save only if importance rating is >= 0.5 (AI Memory Pipeline rule)
                if ($score >= 0.5) {
                    $this->save($candidate, $score, $project);
                }
            }

        } catch (\Exception $e) {
            Log::error("Failed extracting memory: " . $e->getMessage());
        }
    }

    /**
     * Save/upsert candidate memory to DB.
     */
    protected function save(array $candidate, float $score, ?Project $project): void
    {
        Memory::updateOrCreate(
            [
                'user_id'    => Auth::id(),
                'project_id' => $project?->id,
                'key'        => $candidate['key'],
            ],
            [
                'value'            => $candidate['value'],
                'importance_score' => $score,
                'confidence'       => 0.8,
                'source'           => 'extracted',
                'last_used_at'     => now(),
            ]
        );
    }
}
