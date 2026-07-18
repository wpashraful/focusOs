<?php

namespace App\Providers;

use App\Services\AI\AIProviderInterface;
use App\Services\AI\GeminiProvider;
use App\Services\AI\ResourceRetrieverInterface;
use App\Services\AI\MySQLKeywordRetriever;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Event;
use App\Events\TaskCompleted;
use App\Listeners\UpdateDashboardStats;
use App\Events\DailyLogUpdated;
use App\Listeners\RecalculateDailyScore;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AIProviderInterface::class, GeminiProvider::class);
        $this->app->singleton(ResourceRetrieverInterface::class, MySQLKeywordRetriever::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Event::listen(TaskCompleted::class, UpdateDashboardStats::class);
        Event::listen(DailyLogUpdated::class, RecalculateDailyScore::class);
    }
}
