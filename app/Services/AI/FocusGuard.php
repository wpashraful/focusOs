<?php

namespace App\Services\AI;

use App\Models\Project;

class FocusGuard
{
    /**
     * Determine if user should be redirected back on track.
     */
    public function shouldRedirect(string $intent): bool
    {
        return $intent === 'off_topic';
    }

    /**
     * Build guard response keeping user on track.
     */
    public function redirectResponse(?Project $project): string
    {
        $msg = "Hey! Let's stay focused on the task at hand.";

        if ($project) {
            $msg .= " We are currently working on \"{$project->name}\"";
            if ($project->current_phase_name) {
                $msg .= " with the active phase goal: \"{$project->current_phase_goal}\"";
            }
            $msg .= ". Let's finish today's objectives first. What is your status on this?";
        } else {
            $msg .= " Try setting up an active project or workspace so we can get to work.";
        }

        return $msg;
    }
}
