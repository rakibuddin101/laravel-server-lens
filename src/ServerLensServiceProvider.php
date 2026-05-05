<?php

namespace Rakib\ServerLens;

use Rakib\ServerLens\Console\Commands\BlockCommand;
use Rakib\ServerLens\Console\Commands\CheckCommand;
use Rakib\ServerLens\Console\Commands\InstallCommand;
use Rakib\ServerLens\Console\Commands\PruneCommand;
use Rakib\ServerLens\Console\Commands\UnblockCommand;
use Rakib\ServerLens\Http\Middleware\TrafficMonitorMiddleware;
use Rakib\ServerLens\Services\GeoLocationService;
use Rakib\ServerLens\Services\HostMetricReader;
use Rakib\ServerLens\Services\ServerObservabilityService;
use Rakib\ServerLens\Services\TrafficClassifierService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;

class ServerLensServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/server-lens.php', 'server-lens');

        $this->app->singleton(HostMetricReader::class);
        $this->app->singleton(TrafficClassifierService::class);
        $this->app->singleton(GeoLocationService::class);
        $this->app->singleton(ServerObservabilityService::class);
    }

    public function boot(): void
    {
        if (!config('server-lens.enabled', true)) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'server-lens');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        $this->app->make(Kernel::class)->pushMiddleware(TrafficMonitorMiddleware::class);

        if ($this->app->runningInConsole()) {
            $this->publishAssets();
            $this->registerCommands();
            $this->scheduleAutoprune();
        }
    }

    private function publishAssets(): void
    {
        $this->publishes([
            __DIR__ . '/../config/server-lens.php' => config_path('server-lens.php'),
        ], 'server-lens-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'server-lens-migrations');

        $this->publishes([
            __DIR__ . '/../resources/css' => public_path('vendor/server-lens/css'),
            __DIR__ . '/../resources/js'  => public_path('vendor/server-lens/js'),
        ], 'server-lens-assets');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/server-lens'),
        ], 'server-lens-views');
    }

    private function registerCommands(): void
    {
        $this->commands([
            InstallCommand::class,
            PruneCommand::class,
            CheckCommand::class,
            BlockCommand::class,
            UnblockCommand::class,
        ]);
    }

    private function scheduleAutoprune(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('server-lens:prune')->daily();
        });
    }
}
