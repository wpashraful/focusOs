<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GoalController extends Controller
{
    public function index(Project $project)
    {
        $goals = $project->goals()
            ->withCount('tasks')
            ->orderByRaw("FIELD(priority,'high','medium','low')")
            ->get()
            ->append('progress');

        return Inertia::render('Goals/Index', [
            'project' => $project,
            'goals'   => $goals,
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'title'        => ['required', 'string', 'max:200'],
            'description'  => ['nullable', 'string'],
            'target_value' => ['required', 'numeric', 'min:0'],
            'unit'         => ['nullable', 'string', 'max:50'],
            'priority'     => ['required', 'in:low,medium,high'],
        ]);

        $project->goals()->create($validated);

        return back()->with('success', 'Goal created.');
    }

    public function update(Request $request, Project $project, Goal $goal)
    {
        $validated = $request->validate([
            'title'         => ['sometimes', 'string', 'max:200'],
            'description'   => ['nullable', 'string'],
            'target_value'  => ['sometimes', 'numeric', 'min:0'],
            'current_value' => ['sometimes', 'numeric', 'min:0'],
            'unit'          => ['nullable', 'string', 'max:50'],
            'priority'      => ['sometimes', 'in:low,medium,high'],
            'status'        => ['sometimes', 'in:active,completed,paused'],
        ]);

        $goal->update($validated);

        return back()->with('success', 'Goal updated.');
    }

    public function destroy(Project $project, Goal $goal)
    {
        $goal->delete();
        return back()->with('success', 'Goal deleted.');
    }
}
