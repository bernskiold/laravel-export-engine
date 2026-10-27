<?php

use Bernskiold\ExportEngine\Contracts\DescribesExport;
use Bernskiold\ExportEngine\Contracts\RequiresAbility;
use Bernskiold\ExportEngine\Data\PendingExport;
use Bernskiold\ExportEngine\Enums\ExportStatus;
use Bernskiold\ExportEngine\ExportEngine;
use Bernskiold\ExportEngine\Jobs\RunExport;
use Bernskiold\ExportEngine\Models\Export;
use Bernskiold\ExportEngine\Testing\InteractsWithExports;
use Bernskiold\ExportEngine\Tests\Fixtures\BrandsExporter;
use Bernskiold\ExportEngine\Tests\Fixtures\TextExporter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;

uses(InteractsWithExports::class);

class SalesReportExporterForImprovementsTest extends TextExporter implements DescribesExport, RequiresAbility
{
    public static function exportLabel(): string
    {
        return 'Sales report';
    }

    public static function exportAbility(): string
    {
        return 'export sales reports';
    }
}

beforeEach(function () {
    $this->user = signIn();
    $this->user->forceFill(['name' => 'Ada'])->save();

    Gate::define('export sales reports', fn ($user) => $user->name === 'Ada');
});

describe('status', function () {
    it('keeps a queued export requested until a worker starts it', function () {
        Queue::fake();

        $export = Export::generate('Greeting.txt', 'txt', exporter: TextExporter::class)->dispatch();

        expect($export->refresh()->status)->toBe(ExportStatus::Requested)
            ->and($export->started_at)->not->toBeNull();
    });

    it('marks the export as generating when the worker starts it', function () {
        $export = Export::factory()->create(['exporter' => TextExporter::class, 'format' => 'txt']);

        $statusWhileRunning = null;
        ExportEngine::middleware([new class($statusWhileRunning)
        {
            public function __construct(public ?ExportStatus &$status) {}

            public function handle(Export $export, Closure $next): mixed
            {
                $this->status = $export->status;

                return $next($export);
            }
        }]);

        (new RunExport($export))->handle();

        expect($statusWhileRunning)->toBe(ExportStatus::Generating)
            ->and($export->refresh()->status)->toBe(ExportStatus::Available);
    });

    /**
     * A queue can be slow. When the sweep fails an export that waited too
     * long, its job must not bring it back.
     */
    it('does not run an export that is no longer pending', function () {
        $export = Export::factory()->failed()->create(['exporter' => TextExporter::class, 'format' => 'txt']);

        (new RunExport($export))->handle();

        expect($export->refresh()->status)->toBe(ExportStatus::Failed)
            ->and($export->file)->toBeNull();
    });
});

it('puts only one job on the queue for an export', function () {
    Queue::fake();

    $export = Export::generate('Greeting.txt', 'txt', exporter: TextExporter::class);

    $export->dispatch();
    $export->dispatch();

    Queue::assertPushed(RunExport::class, 1);
});

describe('labels', function () {
    it('names an export from its own name, its exporter or its class', function () {
        expect(Export::factory()->create(['name' => 'Q4 sales report', 'exporter' => SalesReportExporterForImprovementsTest::class])->label())->toBe('Q4 sales report')
            ->and(Export::factory()->create(['exporter' => SalesReportExporterForImprovementsTest::class])->label())->toBe('Sales report')
            ->and(Export::factory()->create(['exporter' => BrandsExporter::class])->label())->toBe('Brands Exporter')
            ->and(Export::factory()->create(['exporter' => null])->label())->toBe('Export');
    });
});

describe('abilities', function () {
    it('lets only a person with the ability use the exporter', function () {
        expect(ExportEngine::canExport($this->user, SalesReportExporterForImprovementsTest::class))->toBeTrue()
            ->and(ExportEngine::canExport(makeUser('Grace'), SalesReportExporterForImprovementsTest::class))->toBeFalse()
            ->and(ExportEngine::canExport(null, SalesReportExporterForImprovementsTest::class))->toBeFalse()
            ->and(ExportEngine::canExport(null, TextExporter::class))->toBeTrue();
    });

    it('does not start an export for a person without the ability', function () {
        $pending = new PendingExport(exporter: SalesReportExporterForImprovementsTest::class, downloadName: 'Sales Report', format: 'txt', userId: makeUser('Grace')->id);

        expect(fn () => $pending->start())->toThrow(AuthorizationException::class);
        expect(Export::query()->count())->toBe(0);
    });

    it('fails a queued export when the person lost the ability before it ran', function () {
        Queue::fake();

        $export = Export::generate('Sales Report.txt', 'txt', exporter: SalesReportExporterForImprovementsTest::class)->dispatch();

        $this->user->forceFill(['name' => 'Ada Lovelace'])->save();

        (new RunExport($export->refresh()))->handle();

        expect($export->refresh()->status)->toBe(ExportStatus::Failed)
            ->and($export->failure_reason)->toBe('You no longer have access to this export.');
    });
});

describe('running again', function () {
    it('makes a new export and keeps the old one', function () {
        $first = Export::generate('Greeting.txt', 'txt', exporter: TextExporter::class, options: ['text' => 'Hello'], name: 'Greeting')->dispatchSync()->refresh();

        $second = $first->runAgain()->refresh();

        expect($second->id)->not->toBe($first->id)
            ->and($second->uuid)->not->toBe($first->uuid)
            ->and($second->status)->toBe(ExportStatus::Available)
            ->and($second->name)->toBe('Greeting')
            ->and($second->option('text'))->toBe('Hello')
            ->and($second->file)->toBe("text-exporter/{$second->uuid}.txt")
            ->and($first->refresh()->status)->toBe(ExportStatus::Available)
            ->and($first->file)->toBe("text-exporter/{$first->uuid}.txt");
    });

    it('runs again on the queue when the first export was queued', function () {
        Queue::fake();

        $first = Export::generate('Greeting.txt', 'txt', exporter: TextExporter::class)->dispatch();

        expect($first->runAgain()->is_queued)->toBeTrue();
        Queue::assertPushed(RunExport::class, 2);
    });
});

describe('test helpers', function () {
    it('finds the export that started with an exporter', function () {
        Export::generate('Brands.csv', 'csv', exporter: BrandsExporter::class, filters: ['brands' => ['Aviato']])->dispatchSync();

        $export = $this->assertExportStarted(BrandsExporter::class, fn (Export $export) => $export->filter('brands') === ['Aviato']);

        expect($export->is($this->latestExport()))->toBeTrue()
            ->and($this->rowsOfExport($export))->toBe([['brand_name', 'rank'], ['Aviato', '1']]);
    });

    it('runs an export again the way a worker does', function () {
        $export = Export::generate('Brands.csv', 'csv', exporter: BrandsExporter::class, filters: ['brands' => ['Hooli']])->dispatchSync();

        $export = $this->runExportAsWorker($export);

        expect(auth()->check())->toBeFalse()
            ->and($this->rowsOfExport($export))->toHaveCount(2);
    });
});
