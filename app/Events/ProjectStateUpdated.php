<?php

namespace App\Events;

use App\Models\Project;
use App\Models\Goal;
use App\Models\ProjectStateAudit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectStateUpdated
{
    use Dispatchable, SerializesModels;

    public Project $project;
    public Goal $goal;
    public ProjectStateAudit $audit;

    /**
     * Create a new event instance.
     */
    public function __construct(Project $project, Goal $goal, ProjectStateAudit $audit)
    {
        $this->project = $project;
        $this->goal = $goal;
        $this->audit = $audit;
    }
}
