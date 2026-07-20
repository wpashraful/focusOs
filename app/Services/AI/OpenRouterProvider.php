<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterProvider implements AIProviderInterface
{
    protected string $defaultModel = 'google/gemma-4-26b-a4b-it:free';

    public function __construct()
    {
        $this->apiKey = config('services.openrouter.key', env('OPENROUTER_API_KEY', ''));
    }

    public function chat(array $messages, array $options = []): array
    {
        $model = $this->mapModel($options['model'] ?? $this->defaultModel);
        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens = $options['max_tokens'] ?? 2048;

        $formattedMessages = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'function') {
                $formattedMessages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $msg['name'],
                    'name'         => $msg['name'],
                    'content'      => $msg['content']
                ];
            } elseif ($msg['role'] === 'system') {
                $formattedMessages[] = [
                    'role'    => 'system',
                    'content' => $msg['content']
                ];
            } else {
                $formattedMessages[] = [
                    'role'    => ($msg['role'] === 'model' || $msg['role'] === 'assistant') ? 'assistant' : 'user',
                    'content' => $msg['content']
                ];
            }
        }

        $payload = [
            'model'       => $model,
            'messages'    => $formattedMessages,
            'temperature' => $temperature,
            'max_tokens'  => $maxTokens,
        ];

        if (!empty($options['tools'])) {
            $payload['tools'] = $this->formatTools($options['tools']);
        }

        $url = "https://openrouter.ai/api/v1/chat/completions";
        $startTime = microtime(true);

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'HTTP-Referer'  => 'http://focusos.test',
                'X-Title'       => 'FocusOS',
                'Content-Type'  => 'application/json',
            ])->timeout(30)->post($url, $payload);

            $latency = (int) (round(microtime(true) - $startTime, 3) * 1000);

            if ($response->failed()) {
                throw new \Exception("OpenRouter API Error: " . $response->body());
            }

            $data = $response->json();

            // Check if model returned tool/function calls
            $toolCalls = $data['choices'][0]['message']['tool_calls'] ?? null;

            if ($toolCalls) {
                $toolCall = $toolCalls[0];
                $toolName = $toolCall['function']['name'];
                $toolArgs = json_decode($toolCall['function']['arguments'], true) ?? [];

                $registry = app(\App\Services\AI\ToolRegistry::class);
                $project = $options['project'] ?? null;
                $toolResult = $registry->execute($toolName, $toolArgs, $project);

                // Add assistant tool call turn
                $messages[] = [
                    'role' => 'assistant',
                    'content' => '',
                    'tool_calls' => $toolCalls
                ];

                // Add tool outcome turn
                $messages[] = [
                    'role' => 'function',
                    'name' => $toolName,
                    'content' => json_encode($toolResult)
                ];

                // Recurse to finish interaction
                unset($options['tools']);
                return $this->chat($messages, $options);
            }

            $text = $data['choices'][0]['message']['content'] ?? '';
            $promptTokens = $data['usage']['prompt_tokens'] ?? 0;
            $completionTokens = $data['usage']['completion_tokens'] ?? 0;

            if (isset($options['log_callback'])) {
                $options['log_callback']([
                    'provider'           => 'openrouter',
                    'model'              => $model,
                    'prompt_tokens'      => $promptTokens,
                    'completion_tokens'  => $completionTokens,
                    'latency_ms'         => $latency,
                    'cost'               => $this->calculateCost($model, $promptTokens, $completionTokens),
                    'error'              => null,
                ]);
            }

            return [
                'text'              => $text,
                'prompt_tokens'     => $promptTokens,
                'completion_tokens' => $completionTokens,
                'latency_ms'        => $latency,
            ];

        } catch (\Exception $e) {
            Log::error("OpenRouter Provider Exception: " . $e->getMessage());

            if (isset($options['log_callback'])) {
                $options['log_callback']([
                    'provider'           => 'openrouter',
                    'model'              => $model,
                    'prompt_tokens'      => 0,
                    'completion_tokens'  => 0,
                    'latency_ms'         => (int) (round(microtime(true) - $startTime, 3) * 1000),
                    'cost'               => 0.0,
                    'error'              => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    public function stream(array $messages, array $options = []): mixed
    {
        $model = $this->mapModel($options['model'] ?? $this->defaultModel);
        Log::info("OpenRouter stream start: Model = " . $model);
        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens = $options['max_tokens'] ?? 2048;
        $tokenCallback = $options['token_callback'] ?? null;

        $formattedMessages = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $formattedMessages[] = [
                    'role'    => 'system',
                    'content' => $msg['content']
                ];
            } else {
                $formattedMessages[] = [
                    'role'    => ($msg['role'] === 'model' || $msg['role'] === 'assistant') ? 'assistant' : 'user',
                    'content' => $msg['content']
                ];
            }
        }

        $payload = [
            'model'       => $model,
            'messages'    => $formattedMessages,
            'temperature' => $temperature,
            'max_tokens'  => $maxTokens,
            'stream'      => true,
        ];

        $url = "https://openrouter.ai/api/v1/chat/completions";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "Authorization: Bearer {$this->apiKey}",
            "HTTP-Referer: http://focusos.test",
            "X-Title: FocusOS",
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        
        $buffer = '';
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use ($tokenCallback, &$buffer) {
            $buffer .= $data;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                
                $trimmed = trim($line);
                if (empty($trimmed)) continue;

                if ($trimmed === 'data: [DONE]') {
                    break;
                }

                if (str_starts_with($trimmed, 'data: ')) {
                    $jsonStr = substr($trimmed, 6);
                    $decoded = json_decode($jsonStr, true);
                    $token = $decoded['choices'][0]['delta']['content'] ?? null;
                    if (($token !== null && $token !== '') && $tokenCallback) {
                        $tokenCallback($token);
                    }
                }
            }
            return strlen($data);
        });

        $res = curl_exec($ch);
        if ($res === false) {
            Log::error("OpenRouter Stream CURL Error: " . curl_error($ch));
        }
        curl_close($ch);

        return null;
    }

    public function classify(string $text, array $classes, ?\App\Models\Project $project = null): string
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
            'max_tokens'  => 10
        ]);

        $choice = trim(strtolower($res['text']));

        foreach ($classes as $c) {
            if (str_contains($choice, strtolower($c))) {
                return $c;
            }
        }

        return $classes[0] ?? 'off_topic';
    }

    protected function mapModel(string $model): string
    {
        $mapping = [
            'gemini-3.5-flash'                  => 'google/gemma-4-26b-a4b-it:free',
            'gemini-3.1-flash-lite'             => 'google/gemma-4-26b-a4b-it:free',
            'gemini-2.5-flash'                  => 'google/gemma-4-26b-a4b-it:free',
            'gemini-2.5-pro'                    => 'google/gemma-4-26b-a4b-it:free',
            'gemini-2.0-flash'                  => 'google/gemma-4-26b-a4b-it:free',
            'gemini-2.0-flash-lite'             => 'google/gemma-4-26b-a4b-it:free',
            'gemini-1.5-flash'                  => 'google/gemma-4-26b-a4b-it:free',
            'gemini-1.5-pro'                    => 'google/gemma-4-26b-a4b-it:free',
            
            // Map paid slugs to their free equivalents on OpenRouter
            'google/gemma-4-26b-a4b-it'         => 'google/gemma-4-26b-a4b-it:free',
            'google/gemma-4-31b-it'             => 'google/gemma-4-31b-it:free',
            'meta-llama/llama-3.3-70b-instruct' => 'meta-llama/llama-3.3-70b-instruct:free',
            'meta-llama/llama-3.2-3b-instruct'  => 'meta-llama/llama-3.2-3b-instruct:free',
            'qwen/qwen3-coder'                  => 'qwen/qwen3-coder:free',
            
            'gpt-4o'                            => 'openai/gpt-4o',
            'gpt-4o-mini'                       => 'openai/gpt-4o-mini',
            'gpt-4-turbo'                       => 'openai/gpt-4-turbo',
            'gpt-3.5-turbo'                     => 'openai/gpt-3.5-turbo',
        ];

        return $mapping[$model] ?? (str_contains($model, '/') ? $model : "google/gemma-4-26b-a4b-it:free");
    }

    protected function formatTools(array $tools): array
    {
        $formatted = [];
        foreach ($tools as $tool) {
            $params = $tool['parameters'] ?? [];
            if (!empty($params)) {
                $params = $this->lowercaseTypes($params);
            }
            $formatted[] = [
                'type'     => 'function',
                'function' => [
                    'name'        => $tool['name'],
                    'description' => $tool['description'] ?? '',
                    'parameters'  => $params,
                ]
            ];
        }
        return $formatted;
    }

    protected function lowercaseTypes(array $schema): array
    {
        if (isset($schema['type']) && is_string($schema['type'])) {
            $schema['type'] = strtolower($schema['type']);
        }
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $key => $prop) {
                $schema['properties'][$key] = $this->lowercaseTypes($prop);
            }
        }
        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->lowercaseTypes($schema['items']);
        }
        return $schema;
    }

    protected function calculateCost(string $model, int $prompt, int $completion): float
    {
        // Simple fallback cost calculation
        $inCost = ($prompt / 1000) * 0.000075;
        $outCost = ($completion / 1000) * 0.0003;
        return round($inCost + $outCost, 6);
    }
}
