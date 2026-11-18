<?php

use Bernskiold\ExportEngine\Contracts\FreezesPanes;
use Bernskiold\ExportEngine\Contracts\HasExportSettings;
use Bernskiold\ExportEngine\Contracts\SizesColumns;
use Bernskiold\ExportEngine\Contracts\WithHeader;
use Bernskiold\ExportEngine\Enums\ExportStatus;
use Bernskiold\ExportEngine\Models\Export;
use Bernskiold\ExportEngine\Settings\ExportSettingsContext;
use Bernskiold\ExportEngine\Settings\ToggleSetting;
use Bernskiold\ExportEngine\SimpleExporter;
use Bernskiold\ExportEngine\SpreadsheetExporter;
use Bernskiold\ExportEngine\Support\Staging;
use Bernskiold\ExportEngine\Support\WriterTempFolders;
use Bernskiold\ExportEngine\Tests\Fixtures\BrandsExporter;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobTimedOut;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;

function brandsExport(string $format, array $brands = ['Aviato', 'Hooli']): Export
{
    return Export::generate(
        downloadFileName: "Brands.{$format}",
        format: $format,
        exporter: BrandsExporter::class,
        filters: ['brands' => $brands],
    );
}

/**
 * @return array<int, array<int, mixed>>
 */
function rowsOf(Export $export): array
{
    return SimpleExcelReader::create($export->fullFilePath(), $export->format)->noHeaderRow()->getRows()->all();
}

/**
 * The XML of the first sheet of a workbook.
 */
function sheetXml(Export $export): string
{
    $zip = new ZipArchive;
    $zip->open($export->fullFilePath());

    return $zip->getFromName('xl/worksheets/sheet1.xml');
}

beforeEach(function () {
    signIn();
});

it('writes a CSV file with its header as slugs', function () {
    $export = brandsExport('csv')->dispatchSync()->refresh();

    expect($export->status)->toBe(ExportStatus::Available)
        ->and($export->file)->toBe("brands-exporter/{$export->uuid}.csv")
        ->and(rowsOf($export))->toBe([['brand_name', 'rank'], ['Aviato', '1'], ['Hooli', '2']]);
});

it('writes an Excel file with its header as words', function () {
    $export = brandsExport('xlsx')->dispatchSync()->refresh();

    expect(rowsOf($export))->toBe([['Brand Name', 'Rank'], ['Aviato', 1], ['Hooli', 2]]);
});

it('runs an exporter on its own, the way a test does', function () {
    $export = brandsExport('csv');

    BrandsExporter::make($export)->run();

    expect($export->refresh()->status)->toBe(ExportStatus::Available)
        ->and(rowsOf($export))->toHaveCount(3);
});

it('names the sheet, freezes the header and sizes the columns of an Excel file', function () {
    config(['export-engine.spreadsheets.creator' => 'Export Engine Tests']);

    $export = Export::generate('Styled.xlsx', 'xlsx', exporter: StyledExporterForSpreadsheetTest::class)->dispatchSync()->refresh();

    $xml = sheetXml($export);

    expect($xml)->toContain('<pane xSplit="1" ySplit="1" topLeftCell="B2" activePane="bottomRight" state="frozen"')
        ->and($xml)->toContain('width="30"');

    $zip = new ZipArchive;
    $zip->open($export->fullFilePath());

    expect($zip->getFromName('xl/workbook.xml'))->toContain('name="Styled Brands"')
        ->and($zip->getFromName('docProps/core.xml'))->toContain('<dc:creator>Export Engine Tests</dc:creator>')
        ->and($zip->getFromName('xl/sharedStrings.xml'))->toContain('Aviato');
});

it('continues on a new sheet with the same header', function () {
    $export = Export::generate('Sheets.xlsx', 'xlsx', exporter: SheetsExporterForSpreadsheetTest::class)->dispatchSync()->refresh();

    $second = SimpleExcelReader::create($export->fullFilePath())->fromSheetName('Second')->noHeaderRow()->getRows()->all();

    expect($second)->toBe([['Brand'], ['Hooli']]);
});

it('reads its settings once', function () {
    $export = Export::generate('Settings.csv', 'csv', exporter: SettingsExporterForSpreadsheetTest::class, settings: ['totals' => true]);

    $export->dispatchSync();

    expect(rowsOf($export->refresh()))->toBe([['Aviato'], ['Total']]);
});

class StyledExporterForSpreadsheetTest extends SpreadsheetExporter implements FreezesPanes, SizesColumns, WithHeader
{
    public function header(): array
    {
        return ['Brand', 'Rank'];
    }

    public function freezeColumn(): string
    {
        return 'B';
    }

    public function freezeRow(): int
    {
        return 2;
    }

    public function columnSizes(): array
    {
        return [30, 10];
    }

    protected function sheetName(): string
    {
        return 'Styled Brands';
    }

    protected function data(SimpleExcelWriter $writer): void
    {
        $writer->addRow(['Brand' => 'Aviato', 'Rank' => 1]);
    }
}

it('deletes the temporary folder of an Excel writer when the export fails', function () {
    $tempDirectory = sys_get_temp_dir().'/export-engine-test-'.uniqid();
    config(['export-engine.spreadsheets.temp_directory' => $tempDirectory]);

    $export = Export::generate('Failing.xlsx', 'xlsx', exporter: FailingExporterForSpreadsheetTest::class);

    expect(fn () => FailingExporterForSpreadsheetTest::make($export)->run())
        ->toThrow(RuntimeException::class, 'The database went away.');

    expect(File::allFiles($tempDirectory))->toBe([])
        ->and(File::directories($tempDirectory))->toBe([])
        ->and(Staging::path("failing-exporter-for-spreadsheet-test/{$export->uuid}.xlsx"))->not->toBeFile();

    File::deleteDirectory($tempDirectory);
});

it('builds an Excel file in the temporary directory and deletes its folder after', function () {
    $tempDirectory = sys_get_temp_dir().'/export-engine-test-'.uniqid();
    config(['export-engine.spreadsheets.temp_directory' => $tempDirectory]);

    $foldersWhileWriting = null;
    WatchingExporterForSpreadsheetTest::$watch = function () use ($tempDirectory, &$foldersWhileWriting) {
        $foldersWhileWriting = count(File::directories($tempDirectory));
    };

    $export = Export::generate('Watched.xlsx', 'xlsx', exporter: WatchingExporterForSpreadsheetTest::class)->dispatchSync()->refresh();

    expect($foldersWhileWriting)->toBe(1)
        ->and(rowsOf($export))->toBe([['Aviato']])
        ->and(File::directories($tempDirectory))->toBe([]);

    File::deleteDirectory($tempDirectory);
});

it('deletes the temporary folder of a simple exporter when it fails', function () {
    $tempDirectory = sys_get_temp_dir().'/export-engine-test-'.uniqid();
    config(['export-engine.spreadsheets.temp_directory' => $tempDirectory]);

    $exporter = new class extends SimpleExporter
    {
        public string $directory;

        protected function fileFormat(): string
        {
            return 'xlsx';
        }

        protected function data(SimpleExcelWriter $writer): void
        {
            $writer->addRow(['Aviato']);

            throw new RuntimeException('The database went away.');
        }

        protected function fileName(): string
        {
            return 'feed.xlsx';
        }

        protected function directoryPath(): string
        {
            return $this->directory;
        }
    };
    $exporter->directory = sys_get_temp_dir().'/export-engine-feed-'.uniqid();

    expect(fn () => $exporter->run())->toThrow(RuntimeException::class, 'The database went away.');

    expect(File::directories($tempDirectory))->toBe([])
        ->and($exporter->directory.'/feed.xlsx')->not->toBeFile();

    File::deleteDirectory($tempDirectory);
    File::deleteDirectory($exporter->directory);
});

it('deletes the open temporary folders when a queued job times out', function () {
    config(['export-engine.spreadsheets.temp_directory' => sys_get_temp_dir().'/export-engine-test-'.uniqid()]);

    $folder = WriterTempFolders::create();
    File::put("{$folder}/sheet1.xml", '<row/>');

    event(new JobTimedOut('redis', Mockery::mock(Job::class)));

    expect($folder)->not->toBeDirectory();
});

class SheetsExporterForSpreadsheetTest extends SpreadsheetExporter implements WithHeader
{
    public function header(): array
    {
        return ['Brand'];
    }

    protected function data(SimpleExcelWriter $writer): void
    {
        $writer->addRow(['Brand' => 'Aviato']);

        $this->continueOnNewSheet($writer, 'Second');

        $writer->addRow(['Brand' => 'Hooli']);
    }
}

class SettingsExporterForSpreadsheetTest extends SpreadsheetExporter implements HasExportSettings
{
    public static int $reads = 0;

    public static function exportSettings(ExportSettingsContext $context): array
    {
        static::$reads++;

        return [ToggleSetting::make('totals', 'Totals')];
    }

    protected function data(SimpleExcelWriter $writer): void
    {
        $writer->addRow(['Aviato']);

        if ($this->setting('totals') && $this->setting('totals')) {
            $writer->addRow(['Total']);
        }

        expect(static::$reads)->toBe(1);
    }
}

class FailingExporterForSpreadsheetTest extends SpreadsheetExporter
{
    protected function data(SimpleExcelWriter $writer): void
    {
        $writer->addRow(['Aviato']);

        throw new RuntimeException('The database went away.');
    }
}

class WatchingExporterForSpreadsheetTest extends SpreadsheetExporter
{
    public static ?Closure $watch = null;

    protected function data(SimpleExcelWriter $writer): void
    {
        $writer->addRow(['Aviato']);

        (static::$watch)();
    }
}
