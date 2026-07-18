<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Routine;
use App\Models\RoutineSlot;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoutineController extends Controller
{
    public function edit(Project $project)
    {
        $routine = $project->routine()->with('slots')->first()
            ?? Routine::create(['project_id' => $project->id, 'name' => 'Daily Routine']);

        return Inertia::render('Routine/Edit', [
            'project' => $project,
            'routine' => $routine,
        ]);
    }

    public function storeSlot(Request $request, Project $project, Routine $routine)
    {
        $validated = $request->validate([
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i', 'after:start_time'],
            'label'      => ['required', 'string', 'max:100'],
            'category'   => ['required', 'in:work,break,exercise,learning,personal,other'],
            'color'      => ['nullable', 'string', 'max:20'],
        ]);

        $validated['sort_order'] = $routine->slots()->count();
        $routine->slots()->create($validated);

        return back()->with('success', 'Slot added.');
    }

    public function updateSlot(Request $request, Project $project, Routine $routine, RoutineSlot $slot)
    {
        $validated = $request->validate([
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time'   => ['sometimes', 'date_format:H:i'],
            'label'      => ['sometimes', 'string', 'max:100'],
            'category'   => ['sometimes', 'in:work,break,exercise,learning,personal,other'],
            'color'      => ['nullable', 'string', 'max:20'],
        ]);

        $slot->update($validated);
        return back()->with('success', 'Slot updated.');
    }

    public function destroySlot(Project $project, Routine $routine, RoutineSlot $slot)
    {
        $slot->delete();
        return back();
    }
}
