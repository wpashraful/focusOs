<?php

namespace App\Services\AI;

interface AIProviderInterface
{
    /**
     * Send a single synchronous chat message / messages history to LLM.
     *
     * @param  array  $messages      Array of message arrays: [['role' => 'user|assistant|system', 'content' => '...']]
     * @param  array  $options       Provider config overrides (model, temperature, max_tokens, etc.)
     * @return array                 ['text' => string, 'prompt_tokens' => int, 'completion_tokens' => int, 'latency_ms' => int]
     */
    public function chat(array $messages, array $options = []): array;

    /**
     * Get a generator/callback to stream tokens from the LLM.
     *
     * @param  array  $messages
     * @param  array  $options
     * @return \Generator|callable
     */
    public function stream(array $messages, array $options = []): mixed;

    /**
     * Classify an intent using a lightweight model call.
     *
     * @param  string  $text
     * @param  array   $classes      List of classes to choose from (e.g. ['on_task', 'off_topic', 'future'])
     * @param  \App\Models\Project|null $project Optional project context for provider resolution
     * @return string                The chosen class
     */
    public function classify(string $text, array $classes, ?\App\Models\Project $project = null): string;
}
