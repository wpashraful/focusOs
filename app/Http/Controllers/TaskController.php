<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaskController extends Controller
{
    public function index(Project $project)
    {
        $tasks = $project->tasks()
            ->with(['goal', 'subtasks'])
            ->orderByRaw("FIELD(priority,'urgent','high','medium','low')")
            ->orderBy('due_date')
            ->get();

        $goals = $project->goals()->where('status', 'active')->get(['id', 'title']);

        return Inertia::render('Tasks/Index', [
            'project' => $project,
            'tasks'   => $tasks,
            'goals'   => $goals,
        ]);
    }

    public function today(Request $request)
    {
        // Get the user's first active project (later: from session/preference)
        $user    = $request->user();
        $project = $user->workspaces()->with('projects')->get()
            ->pluck('projects')->flatten()
            ->where('status', 'active')->first();

        $tasks = $project
            ? $project->tasks()->with(['goal', 'subtasks'])->today()->pending()
                ->orderByRaw("FIELD(priority,'urgent','high','medium','low')")
                ->get()
            : collect();

        return Inertia::render('Tasks/Today', [
            'project' => $project,
            'tasks'   => $tasks,
            'date'    => today()->toDateString(),
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'title'              => ['required', 'string', 'max:255'],
            'notes'              => ['nullable', 'string'],
            'goal_id'            => ['nullable', 'exists:goals,id'],
            'priority'           => ['required', 'in:low,medium,high,urgent'],
            'due_date'           => ['nullable', 'date'],
            'scheduled_time'     => ['nullable', 'date_format:H:i'],
            'estimated_minutes'  => ['nullable', 'integer', 'min:1'],
        ]);

        $project->tasks()->create($validated);
        return back()->with('success', 'Task created.');
    }

    public function update(Request $request, Project $project, Task $task)
    {
        $validated = $request->validate([
            'title'             => ['sometimes', 'string', 'max:255'],
            'notes'             => ['nullable', 'string'],
            'goal_id'           => ['nullable', 'exists:goals,id'],
            'priority'          => ['sometimes', 'in:low,medium,high,urgent'],
            'status'            => ['sometimes', 'in:pending,in_progress,done,skipped'],
            'due_date'          => ['nullable', 'date'],
            'scheduled_time'    => ['nullable', 'date_format:H:i'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1'],
        ]);

        // Auto-set completed_at
        if (isset($validated['status']) && $validated['status'] === 'done' && !$task->completed_at) {
            $validated['completed_at'] = now();
        } elseif (isset($validated['status']) && $validated['status'] !== 'done') {
            $validated['completed_at'] = null;
        }

        $task->update($validated);
        return back()->with('success', 'Task updated.');
    }

    public function destroy(Project $project, Task $task)
    {
        $task->delete();
        return back()->with('success', 'Task deleted.');
    }
}
