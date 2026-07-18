<?php

namespace App\Http\Controllers;

use App\Models\DailyTarget;
use App\Models\Goal;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class ProgressController extends Controller
{
    public function index(Request $request)
    {
        $project = $request->user()
            ->workspaces()->with('projects')
            ->get()->pluck('projects')->flatten()
            ->where('status', 'active')->first();

        if (!$project) {
            return Inertia::render('Progress/Index', [
                'project'      => null,
                'dailyScore'   => null,
                'weeklyStats'  => [],
                'goalProgress' => [],
                'metricLogs'   => [],
            ]);
        }

        // ── Daily Score ──────────────────────────────────────────────────
        $todayTotal = $project->tasks()->today()->count();
        $todayDone  = $project->tasks()->today()->where('status', 'done')->count();
        $dailyScore = $todayTotal > 0 ? round(($todayDone / $todayTotal) * 100) : 0;

        // ── Weekly Stats (last 7 days) ───────────────────────────────────
        $weeklyStats = [];
        for ($i = 6; $i >= 0; $i--) {
            $date  = Carbon::today()->subDays($i);
            $label = $date->format('D'); // Mon, Tue, etc.

            $done    = $project->tasks()->whereDate('due_date', $date)->where('status', 'done')->count();
            $total   = $project->tasks()->whereDate('due_date', $date)->count();
            $percent = $total > 0 ? round(($done / $total) * 100) : 0;

            $weeklyStats[] = [
                'date'    => $date->toDateString(),
                'label'   => $label,
                'done'    => $done,
                'total'   => $total,
                'percent' => $percent,
            ];
        }

        // ── Goal Progress ────────────────────────────────────────────────
        $goals = $project->goals()->with('tasks')->get()->map(function ($goal) {
            $total     = $goal->tasks->count();
            $done      = $goal->tasks->where('status', 'done')->count();
            $percent   = $total > 0 ? round(($done / $total) * 100) : 0;
            return [
                'id'      => $goal->id,
                'title'   => $goal->title,
                'done'    => $done,
                'total'   => $total,
                'percent' => $percent,
            ];
        });

        // ── Daily Metric Logs (last 7 days) ─────────────────────────────
        $metricLogs = $project->dailyTargets()->with([
            'logs' => fn($q) => $q->whereDate('log_date', '>=', Carbon::today()->subDays(6))
                                  ->orderBy('log_date'),
        ])->get()->map(function ($target) {
            return [
                'label'        => $target->label,
                'unit'         => $target->unit,
                'target_count' => $target->target_count,
                'logs'         => $target->logs->map(fn($l) => [
                    'date'          => $l->log_date,
                    'achieved'      => $l->achieved_count,
                ]),
            ];
        });

        return Inertia::render('Progress/Index', [
            'project'      => $project->only('id', 'name'),
            'dailyScore'   => $dailyScore,
            'todayDone'    => $todayDone,
            'todayTotal'   => $todayTotal,
            'weeklyStats'  => $weeklyStats,
            'goalProgress' => $goals,
            'metricLogs'   => $metricLogs,
        ]);
    }
}
