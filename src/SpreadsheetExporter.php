<?php

namespace Bernskiold\ExportEngine;

use Bernskiold\ExportEngine\Contracts\ConfiguresWriter;
use Bernskiold\ExportEngine\Contracts\Exporter;
use Bernskiold\ExportEngine\Contracts\FreezesPanes;
use Bernskiold\ExportEngine\Contracts\HasExportSettings;
use Bernskiold\ExportEngine\Contracts\SizesColumns;
use Bernskiold\ExportEngine\Contracts\WithHeader;
use Bernskiold\ExportEngine\Data\ExportedFile;
use Bernskiold\ExportEngine\Data\ListingFilters;
use Bernskiold\ExportEngine\Models\Export;
use Bernskiold\ExportEngine\Support\ExportRunner;
use Bernskiold\ExportEngine\Support\OpenSpout;
use Bernskiold\ExportEngine\Support\Staging;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX;
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * The base of an exporter that writes a CSV or Excel file.
 *
 * Write the rows in `data()`. Add the contracts in `Contracts` to write a
 * header, freeze panes or set column widths. The format comes from the
 * export.
 *
 * @phpstan-consistent-constructor
 */
abstract class SpreadsheetExporter implements Exporter
{
    /**
     * The delimiter of a CSV file. Excel files do not use it.
     */
    public string $delimiter = ',';

    /**
     * @var array<string, mixed>
     */
    protected array $settingValues = [];

    public function __construct(
        public Export $export,
    ) {
        $this->boot();
    }

    public static function make(Export $export): static
    {
        return new static($export);
    }

    /**
     * Do work before the export starts. Override this when necessary.
     */
    protected function boot(): void
    {
        //
    }

    abstract protected function data(SimpleExcelWriter $writer): void;

    /**
     * Write the file and keep it on the export disk.
     */
    public function run(): Export
    {
        return app(ExportRunner::class)->run($this->export, $this);
    }

    public function write(Export $export, string $path): ExportedFile
    {
        File::ensureDirectoryExists($this->directoryPath());

        $writer = OpenSpout::createWriter(
            path: $this->filePath(),
            type: $this->export->format,
            creator: (string) config('export-engine.spreadsheets.creator'),
            delimiter: $this->export->format === 'csv' ? $this->delimiter : null,
            configureWriter: function (WriterInterface $writer) {
                if ($this instanceof ConfiguresWriter) {
                    $this->writerOptions($writer);
                }
            },
        );

        if ($this instanceof WithHeader) {
            $writer->setHeaderStyle($this->headerStyle());
            $writer->addHeader($this->headerFor($writer));
        } else {
            $writer->noHeaderRow();
        }

        $this->freezePanes($writer);

        $excelWriter = $writer->getWriter();

        if ($excelWriter instanceof XLSX\Writer) {
            $excelWriter->getCurrentSheet()->setName(
                str($this->sheetName())->limit(31, '')->toString()
            );
        }

        $this->data($writer);

        if ($this instanceof SizesColumns && $excelWriter instanceof XLSX\Writer) {
            $options = $excelWriter->getOptions();

            foreach (array_values($this->columnSizes()) as $index => $width) {
                $options->setColumnWidth($width, $index + 1);
            }
        }

        $writer->close();

        static::afterExport($this->export);

        return new ExportedFile($this->filePath());
    }

    /**
     * The value the person selected for one of the settings of this
     * exporter. The exporter keeps the value, because it can ask for it on
     * each row.
     *
     * @see HasExportSettings
     */
    protected function setting(string $key): mixed
    {
        if (! array_key_exists($key, $this->settingValues)) {
            $this->settingValues[$key] = $this->export->setting($key);
        }

        return $this->settingValues[$key];
    }

    /**
     * Start a new sheet with the same header and frozen panes as the first
     * sheet. The writer cannot go back to an earlier sheet, so write all the
     * rows of a sheet before you start the next one.
     */
    protected function continueOnNewSheet(SimpleExcelWriter $writer, string $name): void
    {
        $writer->addNewSheetAndMakeItCurrent($name);

        $this->freezePanes($writer);

        if ($this instanceof WithHeader) {
            $writer->addHeader($this->header());
        }
    }

    protected function freezePanes(SimpleExcelWriter $writer): void
    {
        $excelWriter = $writer->getWriter();

        if (! $this instanceof FreezesPanes || ! $excelWriter instanceof XLSX\Writer) {
            return;
        }

        $excelWriter->getCurrentSheet()->setSheetView(
            OpenSpout::frozenSheetView($this->freezeRow(), $this->freezeColumn())
        );
    }

    protected function headerStyle(): Style
    {
        return OpenSpout::headerStyle();
    }

    /**
     * The header row. A CSV file gets the headers as slugs when
     * `slugifyHeadersForCsv()` is true, because tools read CSV headers as
     * column names.
     *
     * @return array<int, string>
     */
    protected function headerFor(SimpleExcelWriter $writer): array
    {
        if (! $this instanceof WithHeader) {
            return [];
        }

        if (! $this->slugifyHeadersForCsv() || ! $this->isCsvWriter($writer->getWriter())) {
            return $this->header();
        }

        return array_map(fn (string $header) => str($header)->slug('_')->toString(), $this->header());
    }

    /**
     * The directory of the files of this exporter, in the staging directory
     * and on the export disk.
     */
    protected function directoryName(): string
    {
        return ExportRunner::directoryFor(static::class);
    }

    protected function fileName(): string
    {
        return $this->export->uuid.'.'.$this->export->format;
    }

    protected function relativePath(): string
    {
        return $this->directoryName().'/'.$this->fileName();
    }

    protected function directoryPath(): string
    {
        return $this->basePath().'/'.$this->directoryName();
    }

    protected function filePath(): string
    {
        return $this->basePath().'/'.$this->relativePath();
    }

    /**
     * OpenSpout writes to a real path, so the writer always writes to the
     * local staging disk. The engine moves the complete file to the export
     * disk.
     */
    protected function basePath(): string
    {
        return Staging::root();
    }

    public function delimiter(string $delimiter): static
    {
        $this->delimiter = $delimiter;

        return $this;
    }

    /**
     * The listing of the table that started the export. Use this in an
     * exporter for more than one table, which reads the table from the
     * export.
     *
     * @param  class-string<ListingFilters>|null  $default
     */
    protected function listing(?string $default = null): ?ListingFilters
    {
        return ListingFilters::resolve($this->export->option('listing', $default), $this->export->filters ?? []);
    }

    /**
     * Narrow the query to the rows of the table that started the export.
     * Null when the table did not give its listing, so the exporter can use
     * its own query.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>|null
     */
    protected function listedQuery(Builder $query): ?Builder
    {
        return $this->listing()?->applyTo($query, $this->export->requester());
    }

    /**
     * Narrow the query to the rows of the one table this exporter is for.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<ListingFilters>  $listing
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyListing(string $listing, Builder $query): Builder
    {
        return $listing::fromArray($this->export->filters ?? [])->applyTo($query, $this->export->requester());
    }

    /**
     * Do work on the file after it is written and before the engine moves
     * it. Override this when necessary.
     */
    protected static function afterExport(Export $export): void
    {
        //
    }

    protected function sheetName(): string
    {
        return __('Data');
    }

    protected function isExcelWriter(WriterInterface $writer): bool
    {
        return $writer instanceof XLSX\Writer;
    }

    protected function isCsvWriter(WriterInterface $writer): bool
    {
        return $writer instanceof CSV\Writer;
    }

    protected function slugifyHeadersForCsv(): bool
    {
        return true;
    }
}
