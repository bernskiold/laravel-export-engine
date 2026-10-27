<?php

use Bernskiold\ExportEngine\Contracts\AuthorizesExport;
use Bernskiold\ExportEngine\Contracts\Exporter;
use Bernskiold\ExportEngine\Data\ExportedFile;
use Bernskiold\ExportEngine\Enums\ExportStatus;
use Bernskiold\ExportEngine\Events\ExportExpired;
use Bernskiold\ExportEngine\Events\ExportFailed;
use Bernskiold\ExportEngine\Events\ExportFinished;
use Bernskiold\ExportEngine\Events\ExportStarted;
use Bernskiold\ExportEngine\Exceptions\ExportNotAllowed;
use Bernskiold\ExportEngine\ExportEngine;
use Bernskiold\ExportEngine\Jobs\RunExport;
use Bernskiold\ExportEngine\Models\Export;
use Bernskiold\ExportEngine\Tests\Fixtures\TextExporter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function textExport(array $attributes = []): Export
{
    return Export::generate(
        downloadFileName: 'Greeting.txt',
        format: 'txt',
        exporter: TextExporter::class,
        options: $attributes['options'] ?? [],
    );
}

beforeEach(function () {
    signIn();
});

it('makes the file of an export that runs in the request', function () {
    $export = textExport(['options' => ['text' => 'Hello, Ada']])->dispatchSync()->refresh();

    expect($export->status)->toBe(ExportStatus::Available)
        ->and($export->is_queued)->toBeFalse()
        ->and($export->disk)->toBe('exports')
        ->and($export->file)->toBe("text-exporter/{$export->uuid}.txt")
        ->and($export->finished_at)->not->toBeNull()
        ->and($export->expires_at->diffInHours(now()->addHours(168), true))->toBeLessThan(1);

    expect(Storage::disk('exports')->get($export->file))->toBe('Hello, Ada');
});

it('moves the file off the staging disk', function () {
    $export = textExport()->dispatchSync()->refresh();

    Storage::disk('staging')->assertMissing("export-engine/{$export->file}");
});

it('keeps the notes of the exporter for the person who asked', function () {
    $export = textExport(['options' => ['notes' => ['Coca-Cola is not included']]])->dispatchSync()->refresh();

    expect($export->notes)->toBe(['Coca-Cola is not included']);
});

it('puts a queued export on the queue of the config', function () {
    config(['export-engine.queue.queue' => 'exports', 'export-engine.queue.tries' => 2]);

    Queue::fake();

    $export = textExport()->dispatch();

    expect($export->is_queued)->toBeTrue()
        ->and($export->status)->toBe(ExportStatus::Requested);

    Queue::assertPushedOn('exports', RunExport::class, fn (RunExport $job) => $job->export->is($export) && $job->tries === 2);
});

it('tells the application when an export starts and finishes', function () {
    Event::fake([ExportStarted::class, ExportFinished::class]);

    $export = textExport()->dispatchSync();

    Event::assertDispatched(ExportStarted::class, fn (ExportStarted $event) => $event->export->is($export));
    Event::assertDispatched(ExportFinished::class, fn (ExportFinished $event) => $event->export->is($export));
});

it('expires the old file when an export runs again', function () {
    Event::fake([ExportExpired::class]);

    $export = textExport()->dispatchSync()->refresh();
    $firstFile = $export->file;

    Storage::disk('exports')->put($firstFile.'.old', 'old');
    $export->forceFill(['file' => $firstFile.'.old'])->save();

    $export->dispatchSync()->refresh();

    Event::assertDispatched(ExportExpired::class);
    Storage::disk('exports')->assertMissing($firstFile.'.old');

    expect($export->status)->toBe(ExportStatus::Available)
        ->and($export->file)->toBe($firstFile);
});

it('keeps the permanent name of an exporter and not its class', function () {
    ExportEngine::exporters(['greeting' => TextExporter::class]);

    $export = textExport()->dispatchSync()->refresh();

    expect($export->getRawOriginal('exporter'))->toBe('greeting')
        ->and($export->exporterClass())->toBe(TextExporter::class)
        ->and($export->file)->toBe("greeting/{$export->uuid}.txt");
});

/**
 * The person sees the failure reason. The message of an exception can tell
 * about the data or the system, so it must not get there.
 */
it('fails with a fixed sentence when the exporter throws', function () {
    $export = Export::generate('Broken.txt', 'txt', exporter: ThrowingExporterForLifecycleTest::class);

    expect(fn () => $export->dispatchSync())->toThrow(RuntimeException::class, 'Database password is hunter2');

    $export->refresh();

    expect($export->status)->toBe(ExportStatus::Failed)
        ->and($export->failure_reason)->toBe('The export could not be completed. Please try again.')
        ->and($export->failed_at)->not->toBeNull();
});

it('fails when the exporter says it wrote a file that is not there', function () {
    $export = Export::generate('Missing.txt', 'txt', exporter: LyingExporterForLifecycleTest::class);

    expect(fn () => $export->dispatchSync())->toThrow(Bernskiold\ExportEngine\Exceptions\ExportNotWritten::class);

    expect($export->refresh()->status)->toBe(ExportStatus::Failed)
        ->and($export->file)->toBeNull();
});

it('checks the person again before the exporter runs', function () {
    Event::fake([ExportFailed::class]);

    $export = Export::generate('Secret.txt', 'txt', exporter: RefusingExporterForLifecycleTest::class)->dispatchSync()->refresh();

    expect($export->status)->toBe(ExportStatus::Failed)
        ->and($export->failure_reason)->toBe('You can no longer export this.')
        ->and(RefusingExporterForLifecycleTest::$wrote)->toBeFalse();

    Event::assertDispatched(ExportFailed::class);
});

it('fails with the reason of an export that is not allowed, and does not try again', function () {
    ExportEngine::middleware([new class
    {
        public function handle(Export $export, Closure $next): mixed
        {
            throw ExportNotAllowed::because('Your access to this client has ended.');
        }
    }]);

    $export = textExport()->dispatchSync()->refresh();

    expect($export->status)->toBe(ExportStatus::Failed)
        ->and($export->failure_reason)->toBe('Your access to this client has ended.');
});

it('runs the export through the middleware in sequence', function () {
    config(['export-engine.middleware' => [GermanExportsForLifecycleTest::class]]);

    textExport()->dispatchSync();

    expect(TextExporter::$wrote)->toBe('de')
        ->and(app()->getLocale())->toBe('en');
});

it('deletes the file when the export fails or expires', function (string $outcome) {
    $export = textExport()->dispatchSync()->refresh();

    $export->{$outcome}();

    Storage::disk('exports')->assertMissing("text-exporter/{$export->uuid}.txt");

    expect($export->refresh()->file)->toBeNull();
})->with(['fail', 'expire']);

it('uses the disk the export was made on after the config changes', function () {
    $export = textExport()->dispatchSync()->refresh();

    Storage::fake('archive');
    config(['export-engine.disk' => 'archive']);

    expect($export->download())->not->toBeNull()
        ->and($export->fileDisk()->exists($export->file))->toBeTrue();
});

it('fails an export whose file is gone when it is downloaded', function () {
    $export = textExport()->dispatchSync()->refresh();

    Storage::disk('exports')->delete($export->file);

    expect($export->download())->toBeNull()
        ->and($export->refresh()->status)->toBe(ExportStatus::Failed);
});

class ThrowingExporterForLifecycleTest implements Exporter
{
    public function write(Export $export, string $path): ExportedFile
    {
        throw new RuntimeException('Database password is hunter2');
    }
}

class LyingExporterForLifecycleTest implements Exporter
{
    public function write(Export $export, string $path): ExportedFile
    {
        return ExportedFile::at("{$path}.txt");
    }
}

class RefusingExporterForLifecycleTest implements AuthorizesExport, Exporter
{
    public static bool $wrote = false;

    public function authorize(Export $export): bool
    {
        return false;
    }

    public function unauthorizedReason(Export $export): string
    {
        return 'You can no longer export this.';
    }

    public function write(Export $export, string $path): ExportedFile
    {
        static::$wrote = true;

        return ExportedFile::at("{$path}.txt");
    }
}

class GermanExportsForLifecycleTest
{
    public function handle(Export $export, Closure $next): mixed
    {
        $previous = app()->getLocale();

        app()->setLocale('de');

        try {
            return $next($export);
        } finally {
            app()->setLocale($previous);
        }
    }
}
