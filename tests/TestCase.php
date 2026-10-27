<?php

namespace Bernskiold\ExportEngine\Tests;

use Bernskiold\ExportEngine\ExportEngine;
use Bernskiold\ExportEngine\ExportEngineServiceProvider;
use Bernskiold\ExportEngine\Support\OpenSpout;
use Bernskiold\ExportEngine\Tests\Fixtures\User;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        ExportEngine::flush();
        OpenSpout::flush();

        Storage::fake('exports');
        Storage::fake('staging');
    }

    protected function getPackageProviders($app): array
    {
        return [
            ExportEngineServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('export-engine.disk', 'exports');
        $app['config']->set('export-engine.staging.disk', 'staging');
        $app['config']->set('queue.default', 'sync');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();

        (include __DIR__.'/../database/migrations/create_exports_table.php.stub')->up();
    }
}
