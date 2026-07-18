<?php

namespace App\Services\AI\Tools;

use App\Models\Project;
use App\Models\Task;

class CreateTask implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name'        => 'create_task',
            'description' => 'Create a new task under the current active project.',
            'parameters'  => [
                'type'       => 'OBJECT',
                'properties' => [
                    'title' => [
                        'type'        => 'STRING',
                        'description' => 'The title/description of the task.'
                    ],
                    'priority' => [
                        'type'        => 'STRING',
                        'enum'        => ['low', 'medium', 'high', 'urgent'],
                        'description' => 'The task priority.'
                    ],
                    'due_date' => [
                        'type'        => 'STRING',
                        'description' => 'ISO date string (YYYY-MM-DD) for when the task is due.'
                    ],
                    'estimated_minutes' => [
                        'type'        => 'INTEGER',
                        'description' => 'Estimated time in minutes to complete this task.'
                    ]
                ],
                'required' => ['title']
            ]
        ];
    }

    public function execute(array $args, ?Project $project): array
    {
        if (!$project) {
            return ['result' => 'Error: No active project context.'];
        }

        $task = $project->tasks()->create([
            'title'             => $args['title'],
            'priority'          => $args['priority'] ?? 'medium',
            'due_date'          => $args['due_date'] ?? today()->toDateString(),
            'estimated_minutes' => $args['estimated_minutes'] ?? null,
            'status'            => 'pending',
        ]);

        return [
            'result' => "Success: Task \"{$task->title}\" (ID: {$task->id}) has been created with priority {$task->priority}."
        ];
    }
}
