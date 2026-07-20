<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaProvider implements AIProviderInterface
{
    protected string $apiUrl;
    protected string $defaultModel;

    public function __construct()
    {
        $this->apiUrl = env('OLLAMA_API_URL', 'http://127.0.0.1:11434');
        $this->defaultModel = env('OLLAMA_MODEL', 'llama3.2:1b');
    }

    public function chat(array $messages, array $options = []): array
    {
        $model = $this->getActiveModel($options['model'] ?? null);

        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens = $options['max_tokens'] ?? 2048;

        $formattedMessages = $this->formatMessages($messages);

        $payload = [
            'model'    => $model,
            'messages' => $formattedMessages,
            'stream'   => false,
            'options'  => [
                'temperature' => $temperature,
                'num_predict' => $maxTokens,
            ]
        ];

        $url = "{$this->apiUrl}/api/chat";
        $startTime = microtime(true);

        try {
            $response = Http::timeout(30)->post($url, $payload);
            $latency = (int) (round(microtime(true) - $startTime, 3) * 1000);

            if ($response->failed()) {
                throw new \Exception("Ollama API Error: " . $response->body());
            }

            $data = $response->json();
            $text = $data['message']['content'] ?? '';
            $promptTokens = $data['prompt_eval_count'] ?? 0;
            $completionTokens = $data['eval_count'] ?? 0;

            if (isset($options['log_callback'])) {
                $options['log_callback']([
                    'provider'           => 'ollama',
                    'model'              => $model,
                    'prompt_tokens'      => $promptTokens,
                    'completion_tokens'  => $completionTokens,
                    'latency_ms'         => $latency,
                    'cost'               => 0.0,
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
            Log::error("Ollama Provider Exception: " . $e->getMessage());
            throw $e;
        }
    }

    public function stream(array $messages, array $options = []): mixed
    {
        $model = $this->getActiveModel($options['model'] ?? null);

        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens = $options['max_tokens'] ?? 2048;
        $tokenCallback = $options['token_callback'] ?? null;

        $formattedMessages = $this->formatMessages($messages);

        $payload = [
            'model'    => $model,
            'messages' => $formattedMessages,
            'stream'   => true,
            'options'  => [
                'temperature' => $temperature,
                'num_predict' => $maxTokens,
            ]
        ];

        $url = "{$this->apiUrl}/api/chat";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        
        $buffer = '';
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use ($tokenCallback, &$buffer) {
            $buffer .= $data;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                
                $trimmed = trim($line);
                if (empty($trimmed)) continue;

                $decoded = json_decode($trimmed, true);
                $token = $decoded['message']['content'] ?? null;
                if (($token !== null && $token !== '') && $tokenCallback) {
                    $tokenCallback($token);
                }
            }
            return strlen($data);
        });

        $res = curl_exec($ch);
        if ($res === false) {
            Log::error("Ollama Stream CURL Error: " . curl_error($ch));
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

        $model = null;
        if ($project && $project->aiSetting) {
            $model = $project->aiSetting->model_name;
        }

        $res = $this->chat($messages, [
            'model'       => $model,
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

    protected function getActiveModel(?string $requestedModel): string
    {
        $model = $requestedModel ?? $this->defaultModel;
        
        if (str_contains($model, 'gemma-4') || str_contains($model, 'gemini')) {
            $model = $this->defaultModel;
        }

        // Verify if the model exists in Ollama. If not, fallback to the first available model!
        try {
            $response = Http::timeout(2)->get("{$this->apiUrl}/api/tags");
            if ($response->successful()) {
                $data = $response->json();
                $models = array_column($data['models'] ?? [], 'name');
                if (!empty($models)) {
                    if (!in_array($model, $models)) {
                        // Fallback to the first available model
                        $model = $models[0];
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore and use default
        }

        return $model;
    }

    protected function formatMessages(array $messages): array
    {
        $formatted = [];
        foreach ($messages as $msg) {
            $role = $msg['role'];
            $content = $msg['content'];

            if ($role === 'system') {
                $formatted[] = [
                    'role'    => 'system',
                    'content' => $content
                ];
            } else {
                $formatted[] = [
                    'role'    => ($role === 'model' || $role === 'assistant') ? 'assistant' : 'user',
                    'content' => $content
                ];
            }
        }
        return $formatted;
    }
}
