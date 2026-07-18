<?php

use App\Http\Controllers\Auth\TelegramLinkController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\SubtaskController;
use App\Http\Controllers\RoutineController;
use App\Http\Controllers\DailyTargetController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Welcome / landing
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin'       => Route::has('login'),
        'canRegister'    => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion'     => PHP_VERSION,
    ]);
});

// Public: called by Telegram bot to confirm linking (no auth, bot-to-server)
Route::post('/telegram/link/confirm', [TelegramLinkController::class, 'confirm'])->name('telegram.confirm');

// ─── Authenticated routes ────────────────────────────────────────────────────
Route::middleware(['auth'])->group(function () {

    // Dashboard
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard', [
            'currentProject' => null,
            'todaysTasks'    => [],
            'routineSlots'   => [],
            'stats'          => [
                'tasks_done_today' => 0,
                'emails_sent'      => 0,
                'linkedin_done'    => 0,
                'content_today'    => 0,
            ],
        ]);
    })->name('dashboard');

    // Profile (Breeze default)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Telegram account linking
    Route::get('/settings/telegram', [TelegramLinkController::class, 'show'])->name('telegram.settings');
    Route::delete('/settings/telegram', [TelegramLinkController::class, 'unlink'])->name('telegram.unlink');

    // Workspaces
    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::patch('/workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::delete('/workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');

    // Projects (nested under workspace)
    Route::get('/workspaces/{workspace}/projects', [ProjectController::class, 'index'])->name('workspaces.projects.index');
    Route::post('/workspaces/{workspace}/projects', [ProjectController::class, 'store'])->name('workspaces.projects.store');
    Route::get('/workspaces/{workspace}/projects/{project}', [ProjectController::class, 'show'])->name('workspaces.projects.show');
    Route::patch('/workspaces/{workspace}/projects/{project}', [ProjectController::class, 'update'])->name('workspaces.projects.update');
    Route::patch('/workspaces/{workspace}/projects/{project}/phase', [ProjectController::class, 'updatePhase'])->name('workspaces.projects.phase');
    Route::delete('/workspaces/{workspace}/projects/{project}', [ProjectController::class, 'destroy'])->name('workspaces.projects.destroy');

    // Legacy sidebar shortcut (redirects to workspaces list)
    Route::get('/projects', fn() => redirect()->route('workspaces.index'))->name('projects.index');

    // ── Goals (per project) ──────────────────────────────────────────────────
    Route::get('/projects/{project}/goals', [GoalController::class, 'index'])->name('goals.index');
    Route::post('/projects/{project}/goals', [GoalController::class, 'store'])->name('goals.store');
    Route::patch('/projects/{project}/goals/{goal}', [GoalController::class, 'update'])->name('goals.update');
    Route::delete('/projects/{project}/goals/{goal}', [GoalController::class, 'destroy'])->name('goals.destroy');

    // ── Tasks (per project) ──────────────────────────────────────────────────
    Route::get('/projects/{project}/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/projects/{project}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::patch('/projects/{project}/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('/projects/{project}/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    // ── Subtasks ─────────────────────────────────────────────────────────────
    Route::post('/tasks/{task}/subtasks', [SubtaskController::class, 'store'])->name('subtasks.store');
    Route::patch('/tasks/{task}/subtasks/{subtask}', [SubtaskController::class, 'update'])->name('subtasks.update');
    Route::delete('/tasks/{task}/subtasks/{subtask}', [SubtaskController::class, 'destroy'])->name('subtasks.destroy');

    // ── Today's Tasks (sidebar) ───────────────────────────────────────────────
    Route::get('/tasks/today', [TaskController::class, 'today'])->name('tasks.today');

    // ── Daily Targets ────────────────────────────────────────────────────────
    Route::post('/projects/{project}/daily-targets', [DailyTargetController::class, 'store'])->name('daily-targets.store');
    Route::patch('/projects/{project}/daily-targets/{dailyTarget}', [DailyTargetController::class, 'update'])->name('daily-targets.update');
    Route::delete('/projects/{project}/daily-targets/{dailyTarget}', [DailyTargetController::class, 'destroy'])->name('daily-targets.destroy');
    Route::post('/projects/{project}/daily-targets/{dailyTarget}/log', [DailyTargetController::class, 'log'])->name('daily-targets.log');

    // ── Routine ───────────────────────────────────────────────────────────────
    Route::get('/projects/{project}/routine', [RoutineController::class, 'edit'])->name('routine.edit');
    Route::post('/projects/{project}/routines/{routine}/slots', [RoutineController::class, 'storeSlot'])->name('routine-slots.store');
    Route::patch('/projects/{project}/routines/{routine}/slots/{slot}', [RoutineController::class, 'updateSlot'])->name('routine-slots.update');
    Route::delete('/projects/{project}/routines/{routine}/slots/{slot}', [RoutineController::class, 'destroySlot'])->name('routine-slots.destroy');

    // ── AI Coach Chat ────────────────────────────────────────────────────────
    Route::get('/chat/{id?}', [ChatController::class, 'index'])->name('chat.index');
    Route::post('/chat', [ChatController::class, 'start'])->name('chat.start');
    Route::post('/chat/{conversation}/messages', [ChatController::class, 'store'])->name('chat.message');
    Route::get('/chat/{conversation}/stream', [ChatController::class, 'stream'])->name('chat.stream');

    // ── Project Resources ────────────────────────────────────────────────────
    Route::get('/projects/{project}/resources', [ResourceController::class, 'index'])->name('resources.index');
    Route::post('/projects/{project}/resources', [ResourceController::class, 'store'])->name('resources.store');
    Route::delete('/projects/{project}/resources/{resource}', [ResourceController::class, 'destroy'])->name('resources.destroy');

    // ── Remaining stubs ───────────────────────────────────────────────────────
    Route::get('/progress', fn() => Inertia::render('ComingSoon', ['page' => 'Progress']))->name('progress.index');
    Route::get('/ideas', fn() => Inertia::render('ComingSoon', ['page' => 'Future Ideas']))->name('ideas.index');
});

Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])->name('telegram.webhook');

require __DIR__.'/auth.php';
