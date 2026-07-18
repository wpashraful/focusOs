<?php

namespace App\Services\AI;

use App\Models\AITool;
use App\Models\Project;
use Illuminate\Support\Facades\Log;

class ToolRegistry
{
    /**
     * Get structural declarations of all active tools to pass to Gemini API.
     */
    public function getDefinitions(): array
    {
        $definitions = [];

        // Load tools from Database
        $tools = AITool::where('is_active', true)->get();

        foreach ($tools as $t) {
            if (class_exists($t->handler_class)) {
                $instance = new $t->handler_class();
                if ($instance instanceof \App\Services\AI\Tools\ToolInterface) {
                    $definitions[] = $instance->definition();
                }
            }
        }

        return $definitions;
    }

    /**
     * Execute a tool by name with arguments and project context.
     */
    public function execute(string $name, array $args, ?Project $project): array
    {
        $tool = AITool::where('name', $name)->where('is_active', true)->first();

        if (!$tool) {
            return ['result' => "Error: Tool \"{$name}\" not registered or active."];
        }

        try {
            $class = $tool->handler_class;
            if (class_exists($class)) {
                $instance = new $class();
                if ($instance instanceof \App\Services\AI\Tools\ToolInterface) {
                    return $instance->execute($args, $project);
                }
            }
            return ['result' => "Error: Handler class \"{$class}\" does not exist."];
        } catch (\Exception $e) {
            Log::error("Failed executing tool {$name}: " . $e->getMessage());
            return ['result' => "Error: Tool execution failed: " . $e->getMessage()];
        }
    }
}
