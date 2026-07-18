<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AIProviderInterface
{
    protected string $apiKey;
    protected string $defaultModel = 'gemini-1.5-flash';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', env('GEMINI_API_KEY', ''));
    }

    public function chat(array $messages, array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultModel;
        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens = $options['max_tokens'] ?? 2048;

        $payload = $this->buildPayload($messages, $temperature, $maxTokens);

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}";

        $startTime = microtime(true);

        try {
            $response = Http::timeout(30)->post($url, $payload);

            $latency = (int) (round(microtime(true) - $startTime, 3) * 1000);

            if ($response->failed()) {
                throw new \Exception("Gemini API Error: " . $response->body());
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Gemini 1.5 doesn't always return exact usageMetadata, but let's check
            $promptTokens = $data['usageMetadata']['promptTokenCount'] ?? 0;
            $completionTokens = $data['usageMetadata']['candidatesTokenCount'] ?? 0;

            // Save LLM log for observability
            if (isset($options['log_callback'])) {
                $options['log_callback']([
                    'provider' => 'gemini',
                    'model' => $model,
                    'prompt_tokens' => $promptTokens,
                    'completion_tokens' => $completionTokens,
                    'latency_ms' => $latency,
                    'cost' => $this->calculateCost($model, $promptTokens, $completionTokens),
                    'error' => null,
                ]);
            }

            return [
                'text'              => $text,
                'prompt_tokens'     => $promptTokens,
                'completion_tokens' => $completionTokens,
                'latency_ms'        => $latency,
            ];

        } catch (\Exception $e) {
            Log::error("Gemini Provider Exception: " . $e->getMessage());

            if (isset($options['log_callback'])) {
                $options['log_callback']([
                    'provider' => 'gemini',
                    'model' => $model,
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                    'latency_ms' => (int) (round(microtime(true) - $startTime, 3) * 1000),
                    'cost' => 0.0,
                    'error' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    public function stream(array $messages, array $options = []): mixed
    {
        $model = $options['model'] ?? $this->defaultModel;
        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens = $options['max_tokens'] ?? 2048;

        $payload = $this->buildPayload($messages, $temperature, $maxTokens);
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:streamGenerateContent?key={$this->apiKey}";

        // Return a generator that can yield text chunks
        return function () use ($url, $payload) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) {
                // Gemini SSE returns chunks in JSON format with parts.text
                // We'll output the data directly to SSE buffer or return it
                echo $data;
                ob_flush();
                flush();
                return strlen($data);
            });
            curl_exec($ch);
            curl_close($ch);
        };
    }

    public function classify(string $text, array $classes): string
    {
        $prompt = "Classify the following user message into exactly one of these categories: [" . implode(', ', $classes) . "].\n"
                . "Respond with ONLY the category name. Do not include extra text, explanation, or punctuation.\n\n"
                . "Message: \"{$text}\"\n"
                . "Category:";

        $messages = [
            ['role' => 'user', 'content' => $prompt]
        ];

        $res = $this->chat($messages, [
            'temperature' => 0.0,
            'max_tokens' => 10
        ]);

        $choice = trim(strtolower($res['text']));

        // Match closest category
        foreach ($classes as $c) {
            if (str_contains($choice, strtolower($c))) {
                return $c;
            }
        }

        return $classes[0] ?? 'off_topic';
    }

    /**
     * Map system/assistant/user roles to Gemini contents payload format
     */
    protected function buildPayload(array $messages, float $temperature, int $maxTokens): array
    {
        $contents = [];
        $systemInstruction = null;

        foreach ($messages as $msg) {
            $role = $msg['role'];
            $content = $msg['content'];

            if ($role === 'system') {
                $systemInstruction = [
                    'parts' => [
                        ['text' => $content]
                    ]
                ];
            } else {
                // Gemini roles are 'user' or 'model'
                $geminiRole = ($role === 'assistant') ? 'model' : 'user';
                $contents[] = [
                    'role'  => $geminiRole,
                    'parts' => [
                        ['text' => $content]
                    ]
                ];
            }
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature'     => $temperature,
                'maxOutputTokens' => $maxTokens,
            ]
        ];

        if ($systemInstruction) {
            $payload['systemInstruction'] = $systemInstruction;
        }

        return $payload;
    }

    /**
     * Simple cost estimation per 1k tokens
     */
    protected function calculateCost(string $model, int $prompt, int $completion): float
    {
        // gemini-1.5-flash: $0.000075 / 1k input, $0.0003 / 1k output
        $inCost = ($prompt / 1000) * 0.000075;
        $outCost = ($completion / 1000) * 0.0003;
        return round($inCost + $outCost, 6);
    }
}
