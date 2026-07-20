<?php

namespace App\Services\AI\Tools;

use App\Models\Project;

class UpdateProjectPhase implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name'        => 'update_project_phase',
            'description' => 'Update the active phase name and phase goal of the project.',
            'parameters'  => [
                'type'       => 'OBJECT',
                'properties' => [
                    'phase_name' => [
                        'type'        => 'STRING',
                        'description' => 'The name of the new phase (e.g. "Execution", "Research", "Testing").'
                    ],
                    'phase_goal' => [
                        'type'        => 'STRING',
                        'description' => 'The specific goal of this new phase (optional).'
                    ]
                ],
                'required' => ['phase_name']
            ]
        ];
    }

    public function execute(array $args, ?Project $project): array
    {
        if (!$project) {
            return ['result' => 'Error: No active project context.'];
        }

        $oldPhase = $project->current_phase_name;
        $project->current_phase_name = $args['phase_name'];
        
        if (isset($args['phase_goal'])) {
            $project->current_phase_goal = $args['phase_goal'];
        }

        $project->save();

        return [
            'result' => "Success: Transitioned project phase from \"{$oldPhase}\" to \"{$project->current_phase_name}\" with goal: \"{$project->current_phase_goal}\"."
        ];
    }
}
