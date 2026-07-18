<?php

namespace App\Http\Controllers;

use App\Models\PhaseSnapshot;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $projects = $workspace->projects()->latest()->get();

        return Inertia::render('Projects/Index', [
            'workspace' => $workspace,
            'projects'  => $projects,
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color'       => ['nullable', 'string', 'max:20'],
            'icon'        => ['nullable', 'string', 'max:10'],
        ]);

        $project = $workspace->projects()->create($validated);

        return redirect()
            ->route('workspaces.projects.show', [$workspace, $project])
            ->with('success', 'Project created.');
    }

    public function show(Workspace $workspace, Project $project)
    {
        $project->load(['phaseSnapshots', 'aiSetting']);

        return Inertia::render('Projects/Show', [
            'workspace' => $workspace,
            'project'   => $project,
        ]);
    }

    public function update(Request $request, Workspace $workspace, Project $project)
    {
        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color'       => ['nullable', 'string', 'max:20'],
            'icon'        => ['nullable', 'string', 'max:10'],
            'status'      => ['nullable', 'in:active,paused,completed'],
        ]);

        $project->update($validated);

        return back()->with('success', 'Project updated.');
    }

    public function updatePhase(Request $request, Workspace $workspace, Project $project)
    {
        $validated = $request->validate([
            'current_phase_name' => ['required', 'string', 'max:150'],
            'current_phase_goal' => ['nullable', 'string'],
            'phase_started_at'   => ['nullable', 'date'],
            'phase_ends_at'      => ['nullable', 'date', 'after_or_equal:phase_started_at'],
        ]);

        // Archive old phase if one exists
        if ($project->current_phase_name) {
            PhaseSnapshot::create([
                'project_id'  => $project->id,
                'phase_name'  => $project->current_phase_name,
                'phase_goal'  => $project->current_phase_goal,
                'started_at'  => $project->phase_started_at,
                'ended_at'    => now()->toDateString(),
            ]);
        }

        $project->update($validated);

        return back()->with('success', 'Phase updated.');
    }

    public function destroy(Workspace $workspace, Project $project)
    {
        $project->delete();

        return redirect()
            ->route('workspaces.projects.index', $workspace)
            ->with('success', 'Project deleted.');
    }
}
