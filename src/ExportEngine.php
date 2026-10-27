<?php

namespace Bernskiold\ExportEngine;

use Bernskiold\ExportEngine\Contracts\Exporter;
use Bernskiold\ExportEngine\Contracts\RequiresAbility;
use Bernskiold\ExportEngine\Http\Controllers\DownloadExportController;
use Bernskiold\ExportEngine\Jobs\RunExport;
use Bernskiold\ExportEngine\Models\Export;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * The configuration of the engine that is code and not config.
 *
 * Call these methods from the `boot()` method of a service provider in your
 * application.
 */
class ExportEngine
{
    /**
     * Permanent names for exporter classes, the same as a morph map.
     *
     * @var array<string, class-string>
     */
    protected static array $exporters = [];

    /**
     * @var array<int, class-string|object>
     */
    protected static array $middleware = [];

    /**
     * Give exporters a permanent name to keep on the export, in place of
     * their class name. Then you can move or rename an exporter class, and
     * the old exports still find it.
     *
     * @param  array<string, class-string>  $exporters
     */
    public static function exporters(array $exporters, bool $merge = true): void
    {
        static::$exporters = $merge ? array_merge(static::$exporters, $exporters) : $exporters;
    }

    /**
     * @return array<string, class-string>
     */
    public static function registeredExporters(): array
    {
        return static::$exporters;
    }

    /**
     * The value to keep on the export for an exporter: its permanent name if
     * it has one, or else its class name.
     */
    public static function exporterAlias(?string $exporter): ?string
    {
        if ($exporter === null) {
            return null;
        }

        $alias = array_search($exporter, static::$exporters, true);

        return is_string($alias) ? $alias : $exporter;
    }

    /**
     * The class of an exporter from the value kept on the export.
     *
     * @return class-string|null
     */
    public static function exporterClass(?string $exporter): ?string
    {
        if ($exporter === null) {
            return null;
        }

        return static::$exporters[$exporter] ?? $exporter;
    }

    /**
     * Wrap each export in more middleware, after the middleware in the
     * config. Each one has a `handle(Export $export, Closure $next)` method.
     *
     * @param  array<int, class-string|object>  $middleware
     */
    public static function middleware(array $middleware): void
    {
        static::$middleware = array_merge(static::$middleware, $middleware);
    }

    /**
     * All the middleware an export runs through, in sequence.
     *
     * @return array<int, class-string|object>
     */
    public static function exportMiddleware(): array
    {
        return array_merge(config('export-engine.middleware', []), static::$middleware);
    }

    /**
     * @return class-string<Export>
     */
    public static function model(): string
    {
        return config('export-engine.model', Export::class);
    }

    public static function newModel(): Export
    {
        $model = static::model();

        return new $model;
    }

    /**
     * @return class-string
     */
    public static function userModel(): string
    {
        return config('export-engine.user_model') ?? config('auth.providers.users.model');
    }

    /**
     * The job that runs an export that does not name a job of its own.
     *
     * @return class-string
     */
    public static function defaultJob(): string
    {
        return RunExport::class;
    }

    /**
     * Register the download route. Call this in a route group to set the
     * domain, the prefix and the middleware.
     *
     * @param  array{uri?: string, name?: string}  $options
     */
    public static function routes(array $options = []): RouteInstance
    {
        return Route::get($options['uri'] ?? 'exports/{export}/download', DownloadExportController::class)
            ->name($options['name'] ?? 'export-engine.download');
    }

    /**
     * Whether a person can use an exporter. An exporter that does not
     * require an ability is open to everyone.
     *
     * @see RequiresAbility
     */
    public static function canExport(?Authenticatable $user, ?string $exporter): bool
    {
        $exporter = static::exporterClass($exporter);

        if ($exporter === null || ! is_a($exporter, RequiresAbility::class, true)) {
            return true;
        }

        return $user !== null && Gate::forUser($user)->allows($exporter::exportAbility());
    }

    /**
     * Whether a class is an exporter the default job can run.
     */
    public static function isExporter(?string $class): bool
    {
        return $class !== null && is_a($class, Exporter::class, true);
    }

    /**
     * Forget all configuration made in code. Use this between tests.
     */
    public static function flush(): void
    {
        static::$exporters = [];
        static::$middleware = [];
    }
}
