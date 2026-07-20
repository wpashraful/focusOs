<?php

namespace App\Services\AI;

class HybridIntentRouter
{
    protected IntentRuleEngine $ruleEngine;
    protected AIProviderInterface $aiProvider;

    public function __construct(IntentRuleEngine $ruleEngine, AIProviderInterface $aiProvider)
    {
        $this->ruleEngine = $ruleEngine;
        $this->aiProvider = $aiProvider;
    }

    /**
     * Route user intent through fast patterns, falling back to LLM classifier.
     *
     * @param  string  $message
     * @param  \App\Models\Conversation|null  $conversation
     * @return array            [intent, confidence, extracted]
     */
    public function route(string $message, ?\App\Models\Conversation $conversation = null): array
    {
        // 1. Pattern Rules Engine (Fast Path)
        $ruleResult = $this->ruleEngine->detect($message);
        if ($ruleResult) {
            return [
                'intent'     => $ruleResult['intent'],
                'confidence' => 1.0,
                'extracted'  => $ruleResult['extracted'],
                'router'     => 'rule_engine',
            ];
        }

        // 2. LLM Intent Extraction Fallback (Slow Path)
        $activeGoalInfo = "";
        if ($conversation && $conversation->project) {
            $goals = $conversation->project->goals()->where('status', 'active')->get();
            foreach ($goals as $goal) {
                $activeGoalInfo .= "- Goal: \"{$goal->title}\" (Current Progress: {$goal->current_value} {$goal->unit}, Target: {$goal->target_value})\n";
            }
        }

        $prompt = "You are an intent extraction engine. Analyze the user's message and the active project goals context. Determine if the user is reporting new progress (adding/removing units) or asking a question/making a general query.\n"
                . "If the user is asking a question (e.g. 'how many leads we have', 'where are we', 'status') or requesting current values, set \"intent\" to \"status_check\", \"operation\" to \"none\", and \"value\" to 0.\n"
                . "Return ONLY a valid JSON object matching this schema. Do not include markdown code blocks, backticks, or extra text.\n"
                . "{\n"
                . "  \"intent\": \"string (progress_report, status_check, greeting, off_topic, future_planning)\",\n"
                . "  \"operation\": \"string (increment, set_total, decrement, none)\",\n"
                . "  \"value\": number (the value to update, default 0),\n"
                . "  \"entity\": \"string (the noun being tracked, e.g. leads, emails, videos)\",\n"
                . "  \"confidence\": number (0.0 to 1.0 rating)\n"
                . "}\n\n"
                . "=== Active Project Goals ===\n"
                . $activeGoalInfo . "\n"
                . "=== User Message ===\n"
                . "\"{$message}\"\n\n"
                . "JSON Output:";

        $messages = [
            ['role' => 'user', 'content' => $prompt]
        ];

        try {
            $res = $this->aiProvider->chat($messages, [
                'project'     => $conversation ? $conversation->project : null,
                'temperature' => 0.0,
                'max_tokens'  => 150
            ]);

            $text = trim($res['text']);
            $text = preg_replace('/^```json\s*/i', '', $text);
            $text = preg_replace('/```$/', '', $text);

            $data = json_decode($text, true);

            if (json_last_error() === JSON_ERROR_NONE && !empty($data['intent'])) {
                $confidence = floatval($data['confidence'] ?? 0.5);
                
                if ($confidence >= 0.8) {
                    return [
                        'intent'     => $data['intent'],
                        'confidence' => $confidence,
                        'extracted'  => [
                            'value'     => intval($data['value'] ?? 0),
                            'operation' => $data['operation'] ?? 'increment',
                            'entity'    => strtolower($data['entity'] ?? 'leads'),
                        ],
                        'router'     => 'llm_extractor',
                    ];
                } else {
                    return [
                        'intent'     => 'need_clarification',
                        'confidence' => $confidence,
                        'extracted'  => [],
                        'router'     => 'llm_extractor',
                    ];
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("LLM Extraction failed: " . $e->getMessage());
        }

        // 3. Simple Fallback Classification if JSON extraction failed
        $context = "";
        if ($conversation) {
            $lastAssistant = $conversation->messages()
                ->where('role', 'assistant')
                ->reorder('created_at', 'desc')
                ->first();
            if ($lastAssistant) {
                $context = "AI Coach last said: \"{$lastAssistant->content}\"\n\n";
            }
        }

        $classificationText = $context . "User reply: \"{$message}\"";
        $classes = ['on_task', 'off_topic', 'future_planning'];
        $chosenClass = $this->aiProvider->classify($classificationText, $classes, $conversation ? $conversation->project : null);

        return [
            'intent'     => $chosenClass,
            'confidence' => 0.70,
            'extracted'  => [],
            'router'     => 'llm_classifier',
        ];
    }
}
