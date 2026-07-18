<?php

namespace App\Services\AI\Tools;

use App\Models\Project;
use App\Services\DynamicRoutineEngine;

class RescheduleRoutine implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name'        => 'reschedule_routine',
            'description' => 'Shift/delay all daily routine blocks starting from now by a specified duration in minutes.',
            'parameters'  => [
                'type'       => 'OBJECT',
                'properties' => [
                    'delay_minutes' => [
                        'type'        => 'INTEGER',
                        'description' => 'Number of minutes to delay/shift the routine schedule.'
                    ]
                ],
                'required' => ['delay_minutes']
            ]
        ];
    }

    public function execute(array $args, ?Project $project): array
    {
        if (!$project) {
            return ['result' => 'Error: No active project context.'];
        }

        $routine = $project->routine()->with('slots')->first();
        if (!$routine) {
            return ['result' => 'Error: No daily routine configured.'];
        }

        $engine = new DynamicRoutineEngine();
        $adjusted = $engine->recalculate($project, $args['delay_minutes']);

        if (empty($adjusted)) {
            return ['result' => 'Error: No slots were modified or routine is empty.'];
        }

        // Stateful updates: write the recalculated times back to the DB slots
        foreach ($adjusted as $adjSlot) {
            $slot = $routine->slots()->find($adjSlot['id']);
            if ($slot) {
                $slot->update([
                    'start_time' => $adjSlot['adjusted_start'],
                    'end_time'   => $adjSlot['adjusted_end'],
                ]);
            }
        }

        return [
            'result' => "Success: Rescheduled daily routine by delaying deep-work and routine slots by {$args['delay_minutes']} minutes."
        ];
    }
}
