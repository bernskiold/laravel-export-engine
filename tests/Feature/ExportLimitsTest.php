<?php

use Bernskiold\ExportEngine\Contracts\EstimatesSize;
use Bernskiold\ExportEngine\Contracts\WithHeader;
use Bernskiold\ExportEngine\Data\PendingExport;
use Bernskiold\ExportEngine\Enums\ExportStatus;
use Bernskiold\ExportEngine\Exceptions\ExportException;
use Bernskiold\ExportEngine\Exceptions\ExportTooLarge;
use Bernskiold\ExportEngine\Models\Export;
use Bernskiold\ExportEngine\SpreadsheetExporter;
use Bernskiold\ExportEngine\Support\GuardedWriter;
use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;

function rowsExport(string $format, int $rows): Export
{
    return Export::generate("Rows.{$format}", $format, exporter: RowsExporterForLimitsTest::class, filters: ['rows' => $rows]);
}

function pendingRowsExport(string $format, int $rows): PendingExport
{
    return new PendingExport(
        exporter: RowsExporterForLimitsTest::class,
        downloadName: 'Rows',
        format: $format,
        filters: ['rows' => $rows],
        userId: auth()->id(),
    );
}

beforeEach(function () {
    signIn();
});

it('fails an Excel export at the row limit and tells the person why', function () {
    config(['export-engine.spreadsheets.max_rows' => 4]);

    $export = rowsExport('xlsx', 4)->dispatchSync()->refresh();

    expect($export->status)->toBe(ExportStatus::Failed)
        ->and($export->failure_reason)->toContain('more than 4 rows')
        ->and($export->file)->toBeNull();
});

it('writes an Excel export that fills the row limit exactly, header included', function () {
    config(['export-engine.spreadsheets.max_rows' => 4]);

    $export = rowsExport('xlsx', 3)->dispatchSync()->refresh();

    expect($export->status)->toBe(ExportStatus::Available)
        ->and(SimpleExcelReader::create($export->fullFilePath())->getRows()->count())->toBe(3);
});

it('does not limit the rows of a CSV export', function () {
    config(['export-engine.spreadsheets.max_rows' => 4]);

    $export = rowsExport('csv', 10)->dispatchSync()->refresh();

    expect($export->status)->toBe(ExportStatus::Available);
});

it('refuses an Excel export over the cell limit before it makes a record', function () {
    config(['export-engine.spreadsheets.max_cells' => 100]);

    // 51 rows of 2 columns is 102 cells.
    expect(fn () => pendingRowsExport('xlsx', 51)->start())
        ->toThrow(ExportTooLarge::class, 'about 51 rows and 2 columns');

    expect(Export::query()->count())->toBe(0);
});

it('refuses an Excel export over the row limit before it makes a record', function () {
    config(['export-engine.spreadsheets.max_rows' => 10]);

    expect(fn () => pendingRowsExport('xlsx', 10)->start())->toThrow(ExportTooLarge::class);

    expect(Export::query()->count())->toBe(0);
});

it('starts an export under the limits, and a CSV export over them', function () {
    config(['export-engine.spreadsheets.max_cells' => 100]);

    expect(pendingRowsExport('xlsx', 50)->start()->status)->toBe(ExportStatus::Available)
        ->and(pendingRowsExport('csv', 51)->start()->status)->toBe(ExportStatus::Available);
});

it('stops writing when the disk is short of space', function () {
    config(['export-engine.spreadsheets.min_free_disk_mb' => PHP_INT_MAX >> 21]);

    $rows = GuardedWriter::DISK_CHECK_EVERY_ROWS + 1;

    expect(fn () => rowsExport('csv', $rows)->dispatchSync())
        ->toThrow(ExportException::class, 'Stopped the export: only');
});

it('writes past the disk check when the disk has space', function () {
    config(['export-engine.spreadsheets.min_free_disk_mb' => 1]);

    $export = rowsExport('csv', GuardedWriter::DISK_CHECK_EVERY_ROWS + 1)->dispatchSync()->refresh();

    expect($export->status)->toBe(ExportStatus::Available);
});

class RowsExporterForLimitsTest extends SpreadsheetExporter implements EstimatesSize, WithHeader
{
    public function header(): array
    {
        return ['Brand', 'Rank'];
    }

    public function estimatedRows(): int
    {
        return (int) $this->export->filter('rows');
    }

    protected function data(SimpleExcelWriter $writer): void
    {
        for ($rank = 1; $rank <= $this->estimatedRows(); $rank++) {
            $writer->addRow(['Brand' => 'Aviato', 'Rank' => $rank]);
        }
    }
}
