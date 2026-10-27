<?php

use Bernskiold\ExportEngine\ExportEngine;
use Bernskiold\ExportEngine\Models\Export;
use Bernskiold\ExportEngine\Support\ExportRunner;
use Bernskiold\ExportEngine\Support\Staging;
use Bernskiold\ExportEngine\Tests\Fixtures\TextExporter;

class ApplicationExportForConfigurationTest extends Export
{
    public function filterLabelsWithoutDefinitions(): array
    {
        return [['key' => 'legacy']];
    }
}

it('uses the model of the config', function () {
    config(['export-engine.model' => ApplicationExportForConfigurationTest::class]);
    signIn();

    $export = Export::factory()->create();

    expect($export)->toBeInstanceOf(ApplicationExportForConfigurationTest::class)
        ->and($export->filterLabels())->toBe([['key' => 'legacy']])
        ->and(ExportEngine::newModel())->toBeInstanceOf(ApplicationExportForConfigurationTest::class);
});

it('uses the table of the config', function () {
    config(['export-engine.table' => 'file_exports']);

    expect((new Export)->getTable())->toBe('file_exports');
});

it('belongs to the user model of the auth provider', function () {
    $user = signIn();

    expect(Export::factory()->create(['user_id' => $user->id])->user->is($user))->toBeTrue();
});

it('keeps a file written outside the staging directory in the directory of its exporter', function () {
    expect(Staging::relativePath('/tmp/deck.pptx', 'decks'))->toBe('decks/deck.pptx')
        ->and(Staging::relativePath(Staging::path('brands/a.csv'), 'decks'))->toBe('brands/a.csv');
});

it('names the directory of an exporter from its permanent name or its class', function () {
    expect(ExportRunner::directoryFor(TextExporter::class))->toBe('text-exporter');

    ExportEngine::exporters(['Sales Report' => TextExporter::class]);

    expect(ExportRunner::directoryFor(TextExporter::class))->toBe('sales-report');
});

it('runs an anonymous exporter', function () {
    signIn();

    $export = Export::generate('Anonymous.csv', 'csv');

    (new class($export) extends Bernskiold\ExportEngine\SpreadsheetExporter
    {
        protected function directoryName(): string
        {
            return 'anonymous';
        }

        protected function data(Spatie\SimpleExcel\SimpleExcelWriter $writer): void
        {
            $writer->addRow(['Aviato']);
        }
    })->run();

    expect($export->refresh()->file)->toBe("anonymous/{$export->uuid}.csv");
});

it('runs only a subclass of the export job', function () {
    signIn();

    $export = Export::factory()->create(['job' => Bernskiold\ExportEngine\Tests\Fixtures\User::class]);

    expect(fn () => $export->jobClass())->toThrow(Bernskiold\ExportEngine\Exceptions\ExportException::class);
});
