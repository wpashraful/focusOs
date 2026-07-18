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
     * @return array            [intent, confidence, extracted]
     */
    public function route(string $message): array
    {
        // 1. Pattern Rules Engine
        $ruleResult = $this->ruleEngine->detect($message);
        if ($ruleResult) {
            return [
                'intent'     => $ruleResult['intent'],
                'confidence' => 1.0,
                'extracted'  => $ruleResult['extracted'],
                'router'     => 'rule_engine',
            ];
        }

        // 2. LLM Fallback Classification
        $classes = ['on_task', 'off_topic', 'future_planning'];
        $chosenClass = $this->aiProvider->classify($message, $classes);

        return [
            'intent'     => $chosenClass,
            'confidence' => 0.85,
            'extracted'  => [],
            'router'     => 'llm_classifier',
        ];
    }
}
