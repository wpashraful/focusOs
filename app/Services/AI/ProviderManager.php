<?php

namespace App\Services\AI;

use App\Models\Project;
use App\Models\AIProvider;
use Illuminate\Support\Facades\Log;

class ProviderManager implements AIProviderInterface
{
    protected array $providers = [];

    public function chat(array $messages, array $options = []): array
    {
        return $this->getProvider($options['project'] ?? null)->chat($messages, $options);
    }

    public function stream(array $messages, array $options = []): mixed
    {
        return $this->getProvider($options['project'] ?? null)->stream($messages, $options);
    }

    public function classify(string $text, array $classes, ?Project $project = null): string
    {
        return $this->getProvider($project)->classify($text, $classes, $project);
    }

    protected function getProvider(?Project $project): AIProviderInterface
    {
        $providerKey = 'gemini';

        if ($project && $project->aiSetting) {
            $dbProvider = AIProvider::find($project->aiSetting->ai_provider_id);
            if ($dbProvider) {
                $providerKey = $dbProvider->provider_key;
            }
        } else {
            // Fallback to env variables
            if (env('AI_PROVIDER') === 'ollama' || env('AI_PROVIDER') === 'local') {
                $providerKey = 'local';
            } elseif (env('OPENROUTER_API_KEY')) {
                $providerKey = 'openrouter';
            }
        }

        if (isset($this->providers[$providerKey])) {
            return $this->providers[$providerKey];
        }

        switch ($providerKey) {
            case 'local':
                $instance = new OllamaProvider();
                break;
            case 'openrouter':
                $instance = new OpenRouterProvider();
                break;
            case 'gemini':
            default:
                $instance = new GeminiProvider();
                break;
        }

        $this->providers[$providerKey] = $instance;
        return $instance;
    }
}
