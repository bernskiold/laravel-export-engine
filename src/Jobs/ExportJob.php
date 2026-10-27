<?php

namespace Bernskiold\ExportEngine\Jobs;

use Bernskiold\ExportEngine\Exceptions\ExportNotAllowed;
use Bernskiold\ExportEngine\ExportEngine;
use Bernskiold\ExportEngine\Models\Export;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * The base of every job that runs an export.
 *
 * The job runs the export through the export middleware, then calls
 * `process()`. It does nothing when the export is no longer pending, for
 * example when the sweep failed it while it waited on the queue. Extend this class for an export that is not a file from an
 * exporter, for example a deck. Most exports use {@see RunExport}.
 */
abstract class ExportJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?int $tries = null;

    public ?int $timeout = null;

    public function __construct(
        public Export $export,
    ) {
        $this->onConnection(config('export-engine.queue.connection'));
        $this->onQueue(config('export-engine.queue.queue'));

        $this->tries = config('export-engine.queue.tries');
        $this->timeout = config('export-engine.queue.timeout');
    }

    /**
     * Make the file of the export and keep it on the export disk.
     */
    abstract public function process(): void;

    public function handle(): void
    {
        if (! $this->export->isPending()) {
            return;
        }

        $this->export->markGenerating();

        try {
            app(Pipeline::class)
                ->send($this->export)
                ->through(ExportEngine::exportMiddleware())
                ->then(fn () => $this->process());
        } catch (ExportNotAllowed $exception) {
            $this->export->fail($exception->getMessage());
        }
    }

    /**
     * Only one job for each export. A second click, or a retry while the
     * first job is on the queue, adds no second job.
     */
    public function uniqueId(): string
    {
        return $this->export->uuid;
    }

    /**
     * The lock stays no longer than an export can stay pending. Thus a
     * worker that stops does not block the export.
     */
    public function uniqueFor(): int
    {
        return Export::abandonedAfterMinutes() * 60;
    }

    /**
     * @return int|array<int, int>|null
     */
    public function backoff(): int|array|null
    {
        return config('export-engine.queue.backoff');
    }

    /**
     * The person sees a fixed sentence. The message of the exception can
     * tell about the data or the system, so it goes only to the log.
     */
    public function failed(?Throwable $exception = null): void
    {
        $this->export->fail(__('The export could not be completed. Please try again.'));
    }
}
