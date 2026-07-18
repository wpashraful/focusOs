<?php

namespace App\Services\AI; // Wait, namespace should be App\Services\AI\Tools

namespace App\Services\AI\Tools;

use App\Models\DailyLog;
use App\Models\Project;

class UpdateDailyLog implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name'        => 'update_daily_log',
            'description' => 'Log or increment progress for a daily metric target (e.g. emails sent, content generated).',
            'parameters'  => [
                'type'       => 'OBJECT',
                'properties' => [
                    'target_label' => [
                        'type'        => 'STRING',
                        'description' => 'The name of the metric (e.g. "emails", "linkedin connections").'
                    ],
                    'count' => [
                        'type'        => 'INTEGER',
                        'description' => 'The count achieved today.'
                    ],
                    'operation' => [
                        'type'        => 'STRING',
                        'enum'        => ['increment', 'set'],
                        'description' => 'Whether to add to the existing count (increment) or overwrite it (set).'
                    ]
                ],
                'required' => ['target_label', 'count']
            ]
        ];
    }

    public function execute(array $args, ?Project $project): array
    {
        if (!$project) {
            return ['result' => 'Error: No active project context.'];
        }

        $target = $project->dailyTargets()
            ->where('label', 'like', '%' . $args['target_label'] . '%')
            ->first();

        if (!$target) {
            return ['result' => "Error: Daily target metric \"{$args['target_label']}\" not found. please define it first."];
        }

        $log = DailyLog::firstOrNew([
            'daily_target_id' => $target->id,
            'log_date'        => today()->toDateString(),
        ]);

        $operation = $args['operation'] ?? 'increment';
        if ($operation === 'set') {
            $log->achieved_count = $args['count'];
        } else {
            $log->achieved_count += $args['count'];
        }

        $log->save();

        // Fire event (Step 6.5)
        event(new \App\Events\DailyLogUpdated($log));

        return [
            'result' => "Success: Logged progress of {$args['count']} for \"{$target->label}\". New total today: {$log->achieved_count}."
        ];
    }
}
