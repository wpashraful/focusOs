<?php

namespace Database\Seeders;

use App\Models\AITool;
use Illuminate\Database\Seeder;

class AIToolsSeeder extends Seeder
{
    public function run(): void
    {
        $tools = [
            [
                'name'          => 'complete_task',
                'description'   => 'Mark a specific task as completed/done.',
                'handler_class' => \App\Services\AI\Tools\CompleteTask::class,
            ],
            [
                'name'          => 'create_task',
                'description'   => 'Create a new task under the current active project.',
                'handler_class' => \App\Services\AI\Tools\CreateTask::class,
            ],
            [
                'name'          => 'update_daily_log',
                'description'   => 'Log or increment progress for a daily metric target.',
                'handler_class' => \App\Services\AI\Tools\UpdateDailyLog::class,
            ],
            [
                'name'          => 'save_future_idea',
                'description'   => 'Save a future business, feature, or task idea for backlog review.',
                'handler_class' => \App\Services\AI\Tools\SaveFutureIdea::class,
            ],
            [
                'name'          => 'reschedule_routine',
                'description'   => 'Shift/delay all daily routine blocks starting from now by a specified duration in minutes.',
                'handler_class' => \App\Services\AI\Tools\RescheduleRoutine::class,
            ],
        ];

        foreach ($tools as $t) {
            AITool::updateOrCreate(['name' => $t['name']], $t);
        }
    }
}
