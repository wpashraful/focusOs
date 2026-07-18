<?php

namespace App\Http\Controllers;

use App\Models\Subtask;
use App\Models\Task;
use Illuminate\Http\Request;

class SubtaskController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $validated = $request->validate([
            'title'      => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $task->subtasks()->create($validated);
        return back()->with('success', 'Subtask added.');
    }

    public function update(Request $request, Task $task, Subtask $subtask)
    {
        $validated = $request->validate([
            'title'      => ['sometimes', 'string', 'max:255'],
            'done'       => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        $subtask->update($validated);
        return back();
    }

    public function destroy(Task $task, Subtask $subtask)
    {
        $subtask->delete();
        return back();
    }
}
