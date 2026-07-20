<?php

namespace App\Services\AI\Tools;

use App\Models\Project;
use App\Models\Goal;
use App\Models\ProjectStateAudit;
use App\Events\ProjectStateUpdated;

class UpdateGoalProgress implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name'        => 'update_goal_progress',
            'description' => 'Update, set, or decrement current progress value of a project goal.',
            'parameters'  => [
                'type'       => 'OBJECT',
                'properties' => [
                    'goal_title' => [
                        'type'        => 'STRING',
                        'description' => 'The title of the goal to update (optional, defaults to first active goal).'
                    ],
                    'value' => [
                        'type'        => 'INTEGER',
                        'description' => 'The progress value.'
                    ],
                    'operation' => [
                        'type'        => 'STRING',
                        'enum'        => ['increment', 'set_total', 'decrement'],
                        'description' => 'Whether to add (increment), overwrite (set_total), or subtract (decrement).'
                    ],
                    'entity' => [
                        'type'        => 'STRING',
                        'description' => 'The entity type reported (e.g. leads, emails, videos).'
                    ]
                ],
                'required' => ['value']
            ]
        ];
    }

    public function execute(array $args, ?Project $project): array
    {
        if (!$project) {
            return ['result' => 'Error: No active project context.'];
        }

        $query = $project->goals();
        
        if (!empty($args['goal_title'])) {
            $query->where('title', 'like', '%' . $args['goal_title'] . '%');
        } else {
            $query->where('status', 'active');
        }

        $goal = $query->first();

        if (!$goal) {
            $goal = $project->goals()->first();
        }

        if (!$goal) {
            return ['result' => 'Error: No goals found for the current project. Please create a goal first.'];
        }

        // 1. Entity Detection & Validation
        $normUnit = trim(strtolower($goal->unit));
        $normEntity = trim(strtolower($args['entity'] ?? ''));

        if (!empty($normEntity)) {
            $unitBase = rtrim($normUnit, 's');
            $entityBase = rtrim($normEntity, 's');
            
            if (!str_contains($unitBase, $entityBase) && !str_contains($entityBase, $unitBase)) {
                return [
                    'result' => "Validation Error: Entity mismatch. You reported progress on \"{$args['entity']}\", but the active goal tracks \"{$goal->unit}\"."
                ];
            }
        }

        $operation = $args['operation'] ?? 'increment';
        $oldValue = $goal->current_value;
        $value = intval($args['value'] ?? 0);

        // 2. Business limit check
        if ($value > 10000) {
            return [
                'result' => "Validation Error: The progress value of {$value} is abnormally high. Please verify your entry."
            ];
        }

        // 3. Apply operation
        if ($operation === 'set_total' || $operation === 'set') {
            $goal->current_value = $value;
        } elseif ($operation === 'decrement') {
            $goal->current_value -= $value;
        } else {
            $goal->current_value += $value;
        }

        // Sanity Check: Capped at 0
        if ($goal->current_value < 0) {
            $goal->current_value = 0;
        }

        $goal->save();

        // 4. Write Audit Trail entry
        $audit = ProjectStateAudit::create([
            'project_id'      => $project->id,
            'conversation_id' => $args['conversation_id'] ?? null,
            'goal_title'      => $goal->title,
            'operation'       => $operation,
            'value'           => $value,
            'previous_value'  => $oldValue,
            'new_value'       => $goal->current_value,
            'entity'          => $args['entity'] ?? $goal->unit,
            'router'          => $args['router'] ?? 'rule_engine',
            'confidence'      => floatval($args['confidence'] ?? 1.0),
        ]);

        // 5. Dispatch Event for Event-Driven Updates
        event(new ProjectStateUpdated($project, $goal, $audit));

        return [
            'result' => "Success: Updated progress of goal \"{$goal->title}\" from {$oldValue} to {$goal->current_value} (Target: {$goal->target_value} {$goal->unit})."
        ];
    }
}
