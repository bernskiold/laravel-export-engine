<?php

namespace Bernskiold\ExportEngine\Models;

use Bernskiold\ExportEngine\Contracts\DefinesExportFields;
use Bernskiold\ExportEngine\Contracts\DescribesExport;
use Bernskiold\ExportEngine\Data\ExportedFile;
use Bernskiold\ExportEngine\Data\ResolvedExportField;
use Bernskiold\ExportEngine\Database\Factories\ExportFactory;
use Bernskiold\ExportEngine\Enums\ExportStatus;
use Bernskiold\ExportEngine\Events\ExportExpired;
use Bernskiold\ExportEngine\Events\ExportFailed;
use Bernskiold\ExportEngine\Events\ExportFinished;
use Bernskiold\ExportEngine\Events\ExportStarted;
use Bernskiold\ExportEngine\Exceptions\ExportException;
use Bernskiold\ExportEngine\ExportEngine;
use Bernskiold\ExportEngine\Jobs\ExportJob;
use Bernskiold\ExportEngine\Settings\ExportSettings;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToRetrieveMetadata;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The record of one export: what to make, who asked for it, where it is in
 * its life and where its file is.
 *
 * The record stays after the file expires, so a person can see what they
 * exported and run it again.
 *
 * @property int $id
 * @property string $uuid
 * @property int|string|null $user_id
 * @property string|null $name
 * @property string|null $exporter
 * @property string|null $job
 * @property string|null $format
 * @property ExportStatus $status
 * @property bool $is_queued
 * @property array<string, mixed>|null $filters
 * @property array<string, mixed>|null $options
 * @property array<string, mixed>|null $settings
 * @property string $download_name
 * @property string|null $disk
 * @property string|null $file
 * @property list<string>|null $notes
 * @property string|null $failure_reason
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property \Illuminate\Support\Carbon|null $failed_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $downloaded_at
 * @property-read Model|null $user
 */
class Export extends Model
{
    /** @use HasFactory<ExportFactory> */
    use HasFactory;

    use MassPrunable;

    protected $guarded = ['id'];

    protected $attributes = [
        'is_queued' => false,
        'status' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => ExportStatus::class,
            'is_queued' => 'boolean',
            'filters' => 'array',
            'options' => 'array',
            'settings' => 'array',
            'notes' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'failed_at' => 'datetime',
            'expires_at' => 'datetime',
            'downloaded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $export) {
            $export->uuid ??= (string) Str::uuid();
            $export->user_id ??= auth()->id();
        });
    }

    public function getTable(): string
    {
        return $this->table ?? config('export-engine.table', 'exports');
    }

    public function getConnectionName(): ?string
    {
        return $this->connection ?? config('export-engine.connection');
    }

    /**
     * The factory of the package. It makes the model in the config, so an
     * application that extends the model can use it too.
     *
     * @return Factory<Export>
     */
    protected static function newFactory(): Factory
    {
        return ExportFactory::new();
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(ExportEngine::userModel(), 'user_id');
    }

    /**
     * Make the record of a new export. It does not start it.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>|null  $settings
     * @param  array<string, mixed>  $attributes  More columns to fill, for columns your own model adds.
     */
    public static function generate(
        string $downloadFileName,
        string $format,
        ?string $exporter = null,
        array $filters = [],
        array $options = [],
        int|string|null $userId = null,
        ?string $jobClass = null,
        ?string $name = null,
        ?array $settings = null,
        array $attributes = [],
    ): static {
        return static::query()->create([
            'download_name' => $downloadFileName,
            'format' => $format,
            'job' => $jobClass,
            'filters' => $filters,
            'options' => $options,
            'settings' => $settings,
            'exporter' => ExportEngine::exporterAlias($exporter),
            'user_id' => $userId ?? auth()->id(),
            'name' => $name,
            ...$attributes,
        ]);
    }

    /**
     * Start the export on the queue.
     */
    public function dispatch(): static
    {
        return $this->start(queued: true);
    }

    /**
     * Run the export now, in this request.
     */
    public function dispatchSync(): static
    {
        return $this->start(queued: false);
    }

    /**
     * Run the export now and send the file back.
     */
    public function run(): ?StreamedResponse
    {
        return $this->dispatchSync()->refresh()->download();
    }

    /**
     * Start the export, or start it again on the same record. When the export
     * ran before, its old file expires first. A queued export stays
     * requested until a worker starts it.
     *
     * @see runAgain() to keep the old record and start a new one.
     */
    public function start(bool $queued = false): static
    {
        if ($this->started_at !== null) {
            $this->expire();
        }

        $this->forceFill([
            'status' => $queued ? ExportStatus::Requested : ExportStatus::Generating,
            'is_queued' => $queued,
            'started_at' => now(),
            'finished_at' => null,
            'failed_at' => null,
            'failure_reason' => null,
            'expires_at' => null,
            'notes' => null,
        ])->save();

        event(new ExportStarted($this));

        $job = $this->jobClass();

        if ($queued) {
            dispatch(new $job($this));
        } else {
            dispatch_sync(new $job($this));
        }

        return $this;
    }

    /**
     * Make a new export from this one and start it. The old record and its
     * history stay as they are.
     */
    public function runAgain(?bool $queued = null): static
    {
        $export = $this->replicate([
            'uuid',
            'status',
            'disk',
            'file',
            'notes',
            'failure_reason',
            'started_at',
            'finished_at',
            'failed_at',
            'expires_at',
            'downloaded_at',
        ]);

        $export->save();

        return $export->start($queued ?? $this->is_queued);
    }

    /**
     * Mark the export as generating, when a worker starts it.
     */
    public function markGenerating(): static
    {
        $this->forceFill(['status' => ExportStatus::Generating])->save();

        return $this;
    }

    /**
     * Mark the export as available, with its file on the export disk.
     *
     * @param  list<string>  $notes
     */
    public function finish(?string $filePath = null, array $notes = [], ?string $disk = null): static
    {
        $this->forceFill([
            'status' => ExportStatus::Available,
            'file' => $filePath ?? $this->file,
            'disk' => $disk ?? $this->disk ?? static::defaultDisk(),
            'notes' => $notes === [] ? $this->notes : $notes,
            'finished_at' => now(),
            'expires_at' => now()->addHours(static::expireAfterHours()),
        ])->save();

        event(new ExportFinished($this));

        return $this;
    }

    /**
     * Mark the export as failed and delete any file it has.
     *
     * @param  string|null  $reason  What the person sees. Do not put the message of an exception here.
     */
    public function fail(?string $reason = null): static
    {
        $this->deleteFile();

        $this->forceFill([
            'status' => ExportStatus::Failed,
            'failure_reason' => $reason,
            'failed_at' => now(),
        ])->save();

        event(new ExportFailed($this));

        return $this;
    }

    /**
     * Mark the export as expired and delete its file.
     */
    public function expire(): static
    {
        $this->deleteFile();

        $this->forceFill(['status' => ExportStatus::Expired])->save();

        event(new ExportExpired($this));

        return $this;
    }

    public function recordDownload(): static
    {
        $this->forceFill(['downloaded_at' => now()])->save();

        return $this;
    }

    public function deleteFile(): static
    {
        if ($this->file === null) {
            return $this;
        }

        $this->fileDisk()->delete($this->file);

        $this->forceFill(['file' => null])->save();

        return $this;
    }

    /**
     * The file as a download. Null when there is no file. When the record
     * names a file that is not on the disk, the export fails.
     */
    public function download(): ?StreamedResponse
    {
        if ($this->file === null) {
            return null;
        }

        try {
            return $this->fileDisk()->download($this->file, $this->download_name);
        } catch (UnableToRetrieveMetadata) {
            $this->fail(__('The file of this export is no longer available.'));

            return null;
        }
    }

    /**
     * Move a file from a local path to the export disk and mark the export
     * as available.
     *
     * The engine deletes the local file only after the disk accepts the new
     * file. A disk with `throw => false` tells of a failed write with a false
     * value and not with an exception. If the engine did not check this, a
     * short problem with S3 could delete the only copy and still show the
     * export as available. The exception lets the job mark the export as
     * failed, and the person can run it again.
     *
     * @param  list<string>  $notes
     */
    public function storeFileFromLocal(string $localPath, string $relativePath, array $notes = []): static
    {
        $stream = fopen($localPath, 'rb');

        if ($stream === false) {
            throw new ExportException("The export was generated but could not be read back from {$localPath}.");
        }

        $disk = static::defaultDisk();

        try {
            $uploaded = Storage::disk($disk)->writeStream($relativePath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($uploaded === false) {
            throw new ExportException("The export was generated but could not be written to the {$disk} disk at {$relativePath}. The staged file has been left at {$localPath}.");
        }

        File::delete($localPath);

        return $this->finish($relativePath, $notes, $disk);
    }

    /**
     * Keep a file the exporter wrote and mark the export as available.
     */
    public function storeExportedFile(ExportedFile $file, string $relativePath): static
    {
        return $this->storeFileFromLocal($file->path, $relativePath, $file->notes);
    }

    /**
     * The disk the file is on. An export keeps the name of its disk, so a
     * change to the config does not lose the files made before it.
     */
    public function fileDisk(): Filesystem
    {
        return Storage::disk($this->disk ?? static::defaultDisk());
    }

    /**
     * The full path of the file. This is only a real path on a local disk,
     * for example in a test with `Storage::fake()`. On S3 it is the key of
     * the object, so application code must use `download()`.
     */
    public function fullFilePath(): string
    {
        return $this->fileDisk()->path($this->file ?? '');
    }

    /**
     * The person who asked for the export.
     */
    public function requester(): ?Authenticatable
    {
        $user = $this->user;

        return $user instanceof Authenticatable ? $user : null;
    }

    public function hasFile(): bool
    {
        return $this->file !== null;
    }

    public function isDownloadable(): bool
    {
        return $this->status === ExportStatus::Available
            && $this->file !== null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    /**
     * What the export is, for a person: its name, or else the label of its
     * exporter, or else the name of the exporter class in words.
     */
    public function label(): string
    {
        if ($this->name !== null && $this->name !== '') {
            return $this->name;
        }

        $exporter = $this->exporterClass();

        if ($exporter !== null && is_a($exporter, DescribesExport::class, true)) {
            return $exporter::exportLabel();
        }

        if ($exporter !== null) {
            return str(class_basename($exporter))->headline()->toString();
        }

        return __('Export');
    }

    /**
     * The exporter class, from the value kept on the export.
     *
     * @return class-string|null
     */
    public function exporterClass(): ?string
    {
        return ExportEngine::exporterClass($this->exporter);
    }

    /**
     * The job that runs the export. Only a subclass of `ExportJob` can run
     * one, so a changed value in the table cannot make the engine create a
     * different class.
     *
     * @return class-string
     */
    public function jobClass(): string
    {
        $job = $this->job ?? ExportEngine::defaultJob();

        if (! is_a($job, ExportJob::class, true)) {
            throw new ExportException("The job {$job} for export {$this->getKey()} is not an export job.");
        }

        return $job;
    }

    public function filter(string $key, mixed $default = []): mixed
    {
        return $this->filters[$key] ?? $default;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function saveOption(string $key, mixed $value): static
    {
        $this->options = [...($this->options ?? []), $key => $value];
        $this->save();

        return $this;
    }

    /**
     * The value the person selected for a setting of the exporter, or the
     * default of the setting when they did not select one. Null when the
     * exporter does not have the setting.
     */
    public function setting(string $key): mixed
    {
        return ExportSettings::forExport($this)->value($key, $this->settings ?? []);
    }

    /**
     * The filters and options of the export, as labels for a person.
     *
     * @return list<ResolvedExportField>
     */
    public function filterLabels(): array
    {
        $exporter = $this->exporterClass();

        if ($exporter !== null && is_a($exporter, DefinesExportFields::class, true)) {
            return $exporter::fieldDefinitions()->resolveForExport($this);
        }

        return $this->filterLabelsWithoutDefinitions();
    }

    /**
     * The labels for an exporter that does not define its fields. Override
     * this to describe the filters of older exporters.
     *
     * @return list<ResolvedExportField>
     */
    public function filterLabelsWithoutDefinitions(): array
    {
        return [];
    }

    /**
     * Exports with a file that is past its expiry time.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function pastExpiry(Builder $query): void
    {
        $query->where('expires_at', '<=', now());
    }

    /**
     * Exports that did not finish, because a worker stopped during the
     * export or the job never ran. Nothing else changes their status, so
     * without the sweep they stay pending always.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function abandoned(Builder $query, ?int $afterMinutes = null): void
    {
        $before = now()->subMinutes($afterMinutes ?? static::abandonedAfterMinutes());

        $query
            ->whereIn('status', ExportStatus::pending())
            ->where(fn (Builder $query) => $query
                ->where('started_at', '<=', $before)
                ->orWhere(fn (Builder $query) => $query
                    ->whereNull('started_at')
                    ->where('created_at', '<=', $before)));
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function failed(Builder $query): void
    {
        $query->where('status', ExportStatus::Failed);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereIn('status', ExportStatus::pending());
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function ownedBy(Builder $query, Authenticatable|Model $user): void
    {
        $query->where('user_id', $user instanceof Model ? $user->getKey() : $user->getAuthIdentifier());
    }

    /**
     * @param  Builder<static>  $query
     */
    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function latestFirst(Builder $query): void
    {
        $query->latest()->latest($query->getModel()->getKeyName());
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function byUuid(Builder $query, string $uuid): void
    {
        $query->where('uuid', $uuid);
    }

    /**
     * The records to prune. None when "prune_after_days" is null.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = config('export-engine.prune_after_days');

        if ($days === null || $days === '') {
            return static::query()->whereKey([]);
        }

        return static::query()->where('created_at', '<', now()->subDays((int) $days));
    }

    public static function defaultDisk(): string
    {
        return config('export-engine.disk', 'local');
    }

    public static function expireAfterHours(): int
    {
        return (int) config('export-engine.expire_after_hours', 168);
    }

    public static function abandonedAfterMinutes(): int
    {
        return (int) config('export-engine.abandoned_after_minutes', 1440);
    }
}
