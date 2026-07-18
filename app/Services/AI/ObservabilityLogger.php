<?php

namespace App\Services\AI;

use App\Models\AILog;
use Illuminate\Support\Facades\Auth;

class ObservabilityLogger
{
    /**
     * Log an AI API transaction.
     *
     * @param  array  $data  [provider, model, action, prompt_tokens, completion_tokens, latency_ms, cost, error, project_id]
     * @return void
     */
    public function log(array $data): void
    {
        AILog::create([
            'user_id'           => Auth::id(),
            'project_id'        => $data['project_id'] ?? null,
            'provider'          => $data['provider'],
            'model'             => $data['model'],
            'action'            => $data['action'] ?? 'chat',
            'prompt_tokens'     => $data['prompt_tokens'] ?? 0,
            'completion_tokens' => $data['completion_tokens'] ?? 0,
            'total_tokens'      => ($data['prompt_tokens'] ?? 0) + ($data['completion_tokens'] ?? 0),
            'latency_ms'        => $data['latency_ms'] ?? 0,
            'cost'              => $data['cost'] ?? 0.0,
            'error'             => $data['error'] ?? null,
        ]);
    }
}
