<?php

namespace Bernskiold\ExportEngine\Testing;

use Bernskiold\ExportEngine\Enums\ExportStatus;
use Bernskiold\ExportEngine\ExportEngine;
use Bernskiold\ExportEngine\Models\Export;
use Closure;
use PHPUnit\Framework\Assert;
use Spatie\SimpleExcel\SimpleExcelReader;

/**
 * Helpers for the tests of an application that uses the engine.
 *
 * Use the trait in a test case, or in Pest with
 * `uses(InteractsWithExports::class)`. The helpers that read files need a
 * local export disk, for example `Storage::fake('exports')`.
 */
trait InteractsWithExports
{
    /**
     * The export that started last.
     */
    protected function latestExport(): Export
    {
        return ExportEngine::model()::query()->latest('id')->firstOrFail();
    }

    /**
     * Assert that an export with this exporter started, and give it back.
     * The callback can make more checks. It must return true.
     *
     * @param  class-string  $exporter
     * @param  (Closure(Export): bool)|null  $callback
     */
    protected function assertExportStarted(string $exporter, ?Closure $callback = null): Export
    {
        $exports = ExportEngine::model()::query()
            ->where('exporter', ExportEngine::exporterAlias($exporter))
            ->latest('id')
            ->get()
            ->filter(fn (Export $export) => $callback === null || $callback($export));

        Assert::assertNotEmpty($exports, "No export with the exporter [{$exporter}] started.");

        return $exports->first();
    }

    /**
     * Run an export again the way a queue worker does: with no signed-in
     * user, through the job and its middleware. This shows a query that
     * uses `auth()->user()` and not the person who asked for the export.
     */
    protected function runExportAsWorker(Export $export): Export
    {
        auth()->forgetGuards();

        $job = $export->jobClass();

        $export->forceFill(['status' => ExportStatus::Requested])->save();

        (new $job($export))->handle();

        return $export->refresh();
    }

    /**
     * The rows of the spreadsheet of an export, with the header row.
     *
     * @return array<int, array<int, mixed>>
     */
    protected function rowsOfExport(Export $export): array
    {
        Assert::assertNotNull($export->file, "Export {$export->getKey()} has no file.");

        return SimpleExcelReader::create($export->fullFilePath(), (string) $export->format)
            ->noHeaderRow()
            ->getRows()
            ->all();
    }
}
