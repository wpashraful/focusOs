<?php

namespace App\Services\AI\Tools;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\Log;

class CompleteTask implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name'        => 'complete_task',
            'description' => 'Mark a specific task as completed/done.',
            'parameters'  => [
                'type'       => 'OBJECT',
                'properties' => [
                    'task_id' => [
                        'type'        => 'INTEGER',
                        'description' => 'The ID of the task to complete.'
                    ],
                    'title' => [
                        'type'        => 'STRING',
                        'description' => 'The title of the task to complete (if ID is not known).'
                    ]
                ],
                'required' => []
            ]
        ];
    }

    public function execute(array $args, ?Project $project): array
    {
        if (!$project) {
            return ['result' => 'Error: No active project context.'];
        }

        $query = $project->tasks();

        if (!empty($args['task_id'])) {
            $task = $query->find($args['task_id']);
        } elseif (!empty($args['title'])) {
            $task = $query->where('title', 'like', '%' . $args['title'] . '%')
                ->where('status', '!=', 'done')
                ->first();
        } else {
            return ['result' => 'Error: You must provide a task_id or title.'];
        }

        if (!$task) {
            return ['result' => 'Error: Task not found.'];
        }

        $task->update([
            'status'       => 'done',
            'completed_at' => now(),
        ]);

        // Fire event (Step 6.5)
        event(new \App\Events\TaskCompleted($task));

        return [
            'result' => "Success: Task \"{$task->title}\" (ID: {$task->id}) has been marked as completed."
        ];
    }
}
