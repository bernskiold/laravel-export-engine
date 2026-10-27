<?php

namespace Bernskiold\ExportEngine\Data;

use Bernskiold\ExportEngine\ExportEngine;
use Bernskiold\ExportEngine\Models\Export;
use Bernskiold\ExportEngine\Settings\ExportSettings;
use Bernskiold\ExportEngine\Settings\ExportSettingsContext;
use Bernskiold\ExportEngine\Support\ExportFileName;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * An export that a component made but did not start yet.
 *
 * When the exporter has settings, the engine holds the export in the cache
 * under a token while the person selects them. The form only gets the
 * token. Thus the browser cannot change the exporter or the filters, and
 * the engine adds no record to the exports table until the person starts
 * the export.
 */
class PendingExport
{
    /**
     * @param  class-string|null  $exporter
     * @param  string  $downloadName  What the export is, for example "Brand Overview". The engine changes a slug into words.
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $options
     * @param  class-string|null  $jobClass  The job that runs the export. Null uses the default job.
     * @param  list<string>  $filenameLabels  The filters of the page, most important first, for the file name.
     * @param  array<string, mixed>  $attributes  More columns to fill, for columns your own model adds.
     */
    public function __construct(
        public readonly ?string $exporter,
        public readonly string $downloadName,
        public readonly string $format,
        public readonly array $filters = [],
        public readonly array $options = [],
        public readonly bool $queued = false,
        public readonly ?string $jobClass = null,
        public readonly ?string $name = null,
        public readonly int|string|null $userId = null,
        public readonly array $filenameLabels = [],
        public readonly array $attributes = [],
    ) {}

    public function settings(): ExportSettings
    {
        return ExportSettings::for($this->exporter, new ExportSettingsContext(
            format: $this->format,
            filters: $this->filters,
            options: $this->options,
            user: $this->user(),
        ));
    }

    /**
     * Whether a job of its own runs the export, for example a deck, and not
     * the default job.
     */
    public function usesCustomJob(): bool
    {
        return $this->jobClass !== null && ! is_a($this->jobClass, ExportEngine::defaultJob(), true);
    }

    /**
     * Make the record of the export and start it.
     *
     * @param  array<string, mixed>|null  $settings
     */
    public function start(?array $settings = null): Export
    {
        if (! ExportEngine::canExport($this->user(), $this->exporter)) {
            throw new AuthorizationException(__('You do not have access to this export.'));
        }

        $export = ExportEngine::model()::generate(
            downloadFileName: ExportFileName::make($this->downloadName, $this->filenameLabels)->toString($this->format),
            format: $this->format,
            exporter: $this->exporter,
            filters: $this->filters,
            options: $this->options,
            userId: $this->userId,
            jobClass: $this->jobClass,
            name: $this->name,
            settings: $settings,
            attributes: $this->attributes,
        );

        $this->queued ? $export->dispatch() : $export->dispatchSync();

        return $export->fresh();
    }

    /**
     * Hold the export while the person selects its settings. Use the token
     * to get it back.
     */
    public function hold(): string
    {
        $token = (string) Str::uuid();

        static::cache()->put(static::cacheKey($token), $this, now()->addMinutes((int) config('export-engine.pending.minutes', 60)));

        return $token;
    }

    /**
     * The export held under a token. Null when the token expired, or when
     * the export is for a different person.
     */
    public static function find(string $token, ?Authenticatable $user): ?static
    {
        $pending = static::cache()->get(static::cacheKey($token));

        if (! $pending instanceof static || $user === null || (string) $pending->userId !== (string) $user->getAuthIdentifier()) {
            return null;
        }

        return $pending;
    }

    public static function release(string $token): void
    {
        static::cache()->forget(static::cacheKey($token));
    }

    public function user(): ?Authenticatable
    {
        if ($this->userId === null) {
            return null;
        }

        $model = ExportEngine::userModel();

        return $model::query()->find($this->userId);
    }

    protected static function cache(): Repository
    {
        return Cache::store(config('export-engine.pending.cache_store'));
    }

    protected static function cacheKey(string $token): string
    {
        return "export-engine:pending:{$token}";
    }
}
