<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use App\Models\DailyTarget;
use App\Models\Project;
use Illuminate\Http\Request;

class DailyTargetController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'label'        => ['required', 'string', 'max:100'],
            'target_count' => ['required', 'integer', 'min:1'],
            'unit'         => ['nullable', 'string', 'max:50'],
            'goal_id'      => ['nullable', 'exists:goals,id'],
        ]);

        $project->dailyTargets()->create($validated);
        return back()->with('success', 'Daily target created.');
    }

    public function update(Request $request, Project $project, DailyTarget $dailyTarget)
    {
        $validated = $request->validate([
            'label'        => ['sometimes', 'string', 'max:100'],
            'target_count' => ['sometimes', 'integer', 'min:1'],
            'unit'         => ['nullable', 'string', 'max:50'],
            'is_active'    => ['sometimes', 'boolean'],
        ]);

        $dailyTarget->update($validated);
        return back()->with('success', 'Daily target updated.');
    }

    public function destroy(Project $project, DailyTarget $dailyTarget)
    {
        $dailyTarget->delete();
        return back();
    }

    /** Log today's count for a daily target */
    public function log(Request $request, Project $project, DailyTarget $dailyTarget)
    {
        $validated = $request->validate([
            'achieved_count' => ['required', 'integer', 'min:0'],
            'notes'          => ['nullable', 'string'],
        ]);

        DailyLog::updateOrCreate(
            ['daily_target_id' => $dailyTarget->id, 'log_date' => today()],
            $validated
        );

        return back()->with('success', 'Progress logged.');
    }
}
