<?php

namespace App\Services\AI;

use App\Models\Project;

class ContextBuilder
{
    protected TokenBudget $budget;
    protected ResourceRetrieverInterface $retriever;

    public function __construct(TokenBudget $budget, ResourceRetrieverInterface $retriever)
    {
        $this->budget = $budget;
        $this->retriever = $retriever;
    }

    /**
     * Gather and format project context under a token limit.
     *
     * @param  Project  $project
     * @param  int      $totalBudget   Target limit for this context chunk (default: 2000)
     * @param  string|null $userMessage Optional message to trigger keyword document lookup
     * @return string
     */
    public function build(Project $project, int $totalBudget = 2000, ?string $userMessage = null): string
    {
        // 1. Build individual context components
        $sections = [
            'project'       => $this->buildProjectSection($project),
            'goals'         => $this->buildGoalsSection($project),
            'tasks'         => $this->buildTasksSection($project),
            'routine'       => $this->buildRoutineSection($project),
            'daily_targets' => $this->buildDailyTargetsSection($project),
            'memories'      => $this->buildMemoriesSection($project),
            'resources'     => $this->buildResourcesSection($project, $userMessage),
        ];

        // 2. Format them together into one context block, keeping budget safe
        $output = "=== PROJECT CONTEXT ===\n";

        foreach ($sections as $name => $content) {
            if (empty(trim($content))) {
                continue;
            }

            // Estimate current output + next section size
            $nextChunk = "[$name]\n" . $content . "\n";
            if ($this->budget->fits($output . $nextChunk, $totalBudget)) {
                $output .= $nextChunk;
            } else {
                // If section is too big, try trimming it to fit remaining budget
                $remaining = $totalBudget - $this->budget->estimate($output) - 20;
                if ($remaining > 100) {
                    $output .= "[$name] (partially trimmed):\n" . $this->budget->trim($content, $remaining) . "\n";
                }
                break;
            }
        }

        return $output;
    }

    protected function buildProjectSection(Project $project): string
    {
        $out = "Project: {$project->name}\n"
             . "Description: " . ($project->description ?? 'N/A') . "\n";

        if ($project->current_phase_name) {
            $out .= "Active Phase: {$project->current_phase_name}\n"
                  . "Phase Goal: {$project->current_phase_goal}\n";
        }

        if ($project->phase_started_at || $project->phase_ends_at) {
            $out .= "Timeline: " . ($project->phase_started_at?->toDateString() ?? '?')
                  . " to " . ($project->phase_ends_at?->toDateString() ?? '?') . "\n";
        }

        return $out;
    }

    protected function buildGoalsSection(Project $project): string
    {
        $goals = $project->goals()->where('status', 'active')->get();
        if ($goals->isEmpty()) return "";

        $out = "Active Goals:\n";
        foreach ($goals as $goal) {
            $out .= "- {$goal->title}: {$goal->current_value} / {$goal->target_value} {$goal->unit} ({$goal->progress}% completed) [Priority: {$goal->priority}]\n";
        }
        return $out;
    }

    protected function buildTasksSection(Project $project): string
    {
        $todayTasks = $project->tasks()->today()->get();
        if ($todayTasks->isEmpty()) return "";

        $out = "Today's Tasks:\n";
        foreach ($todayTasks as $task) {
            $statusLabel = $task->status === 'done' ? '[DONE]' : '[PENDING]';
            $timeLabel = $task->scheduled_time ? " at {$task->scheduled_time}" : "";
            $out .= "- {$statusLabel} {$task->title}{$timeLabel} (Priority: {$task->priority})\n";
        }
        return $out;
    }

    protected function buildRoutineSection(Project $project): string
    {
        $routine = $project->routine()->with('slots')->first();
        if (! $routine || $routine->slots->isEmpty()) return "";

        $now = now();
        $curMin = $now->getHours() * 60 + $now->getMinutes();

        $out = "Daily Routine Blocks:\n";
        foreach ($routine->slots as $slot) {
            $startMin = $this->timeToMin($slot->start_time);
            $endMin = $this->timeToMin($slot->end_time);

            $activeLabel = ($curMin >= $startMin && $curMin < $endMin) ? " *NOW ACTIVE*" : "";

            $out .= "- {$slot->start_time} - {$slot->end_time}: {$slot->label} ({$slot->category}){$activeLabel}\n";
        }
        return $out;
    }

    protected function buildDailyTargetsSection(Project $project): string
    {
        $targets = $project->dailyTargets()->with('todayLog')->get();
        if ($targets->isEmpty()) return "";

        $out = "Daily Action Metrics:\n";
        foreach ($targets as $t) {
            $achieved = $t->todayLog?->achieved_count ?? 0;
            $out .= "- Target: {$t->label} (Target: {$t->target_count} {$t->unit}/day, Logged today: {$achieved})\n";
        }
        return $out;
    }

    protected function buildMemoriesSection(Project $project): string
    {
        // Query user memories (project specific or global)
        $memories = \App\Models\Memory::where('user_id', $project->workspace->owner_id)
            ->where(function($q) use ($project) {
                $q->where('project_id', $project->id)->orWhereNull('project_id');
            })
            ->get();

        if ($memories->isEmpty()) {
            return "";
        }

        // Custom sort by (importance * 0.6) + (recency * 0.4)
        $sorted = $memories->map(function ($memory) {
            $daysSinceUpdate = now()->diffInDays($memory->updated_at);
            $recencyScore = 1 / (1 + $daysSinceUpdate);
            $score = ($memory->importance_score * 0.6) + ($recencyScore * 0.4);
            $memory->temp_score = $score;
            return $memory;
        })
        ->sortByDesc('temp_score')
        ->take(15);

        $out = "Remembered Context / User Preferences:\n";
        foreach ($sorted as $m) {
            $out .= "- {$m->key}: {$m->value}\n";
            // Update last_used_at timestamp
            $m->update(['last_used_at' => now()]);
        }

        return $out;
    }

    protected function buildResourcesSection(Project $project, ?string $userMessage): string
    {
        if (empty($userMessage)) {
            return "";
        }

        // Search top 5 relevant document chunks
        $chunks = $this->retriever->retrieve($userMessage, $project->id, 5);

        if ($chunks->isEmpty()) {
            return "";
        }

        $out = "Relevant Document / Knowledge Base Excerpts:\n";
        foreach ($chunks as $c) {
            $out .= "[Doc: {$c->resource->name} (chunk #{$c->chunk_index})]:\n"
                  . "{$c->content}\n"
                  . "----------------------------------------\n";
        }

        return $out;
    }

    private function timeToMin(string $t): int
    {
        [$h, $m] = array_map('intval', explode(':', $t));
        return $h * 60 + $m;
    }
}
