<?php

namespace App\Services\AI\Tools;

use App\Models\Project;

interface ToolInterface
{
    /**
     * Return structural JSON-schema definition of the tool parameters for Gemini/LLM.
     */
    public function definition(): array;

    /**
     * Execute the tool with user-extracted arguments and active project context.
     * Must return an array with key 'result' (e.g. string or array/struct).
     */
    public function execute(array $args, ?Project $project): array;
}
