<?php

namespace Bernskiold\ExportEngine;

use Bernskiold\ExportEngine\Console\SweepExportsCommand;
use Bernskiold\ExportEngine\Events\ExportFailed;
use Bernskiold\ExportEngine\Events\ExportFinished;
use Bernskiold\ExportEngine\Listeners\SendExportNotification;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ExportEngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/export-engine.php', 'export-engine');
    }

    public function boot(): void
    {
        Event::listen([ExportFinished::class, ExportFailed::class], SendExportNotification::class);

        if (config('export-engine.policy') !== null) {
            Gate::policy(ExportEngine::model(), config('export-engine.policy'));
        }

        if ($this->app->runningInConsole()) {
            $this->commands([SweepExportsCommand::class]);

            $this->registerPublishing();

            AboutCommand::add('Export Engine', fn (): array => [
                'Model' => ExportEngine::model(),
                'Disk' => config('export-engine.disk'),
                'Staging Disk' => config('export-engine.staging.disk'),
                'Queue' => config('export-engine.queue.queue') ?? 'default',
                'Exporters' => (string) count(ExportEngine::registeredExporters()),
            ]);
        }
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/export-engine.php' => config_path('export-engine.php'),
        ], 'export-engine-config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_exports_table.php.stub' => $this->app->databasePath(
                'migrations/'.date('Y_m_d_His').'_create_exports_table.php'
            ),
        ], 'export-engine-migrations');
    }
}
