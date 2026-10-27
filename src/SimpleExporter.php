<?php

namespace Bernskiold\ExportEngine;

use Bernskiold\ExportEngine\Contracts\WithHeader;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * Writes a CSV or Excel file to a local path, with no export record. Use it
 * for files that go to other systems and not to a person, for example a
 * data feed.
 */
abstract class SimpleExporter
{
    /**
     * What `run()` gives back. This is the file the writer wrote, unless
     * `afterExport()` changes it to a different file, for example a
     * converted copy.
     */
    protected ?string $outputPath = null;

    /**
     * @param  mixed  ...$arguments
     */
    public static function make(...$arguments): static
    {
        return new static(...$arguments);
    }

    /**
     * Do work before the export starts. Override this when necessary.
     */
    protected function boot(): void
    {
        //
    }

    public function run(): string
    {
        $this->boot();

        File::ensureDirectoryExists($this->directoryPath());

        $writer = SimpleExcelWriter::create(
            file: $this->filePath(),
            type: $this->fileFormat(),
            delimiter: ',',
        );

        if ($this instanceof WithHeader) {
            $writer->addHeader($this->header());
        } else {
            $writer->noHeaderRow();
        }

        $this->data($writer);
        $writer->close();

        $this->outputPath = $this->filePath();

        $this->afterExport();

        return $this->outputPath;
    }

    protected function fileFormat(): string
    {
        return 'csv';
    }

    /**
     * Do work on the file after it is written. Override this when necessary.
     */
    protected function afterExport(): void
    {
        //
    }

    abstract protected function data(SimpleExcelWriter $writer): void;

    abstract protected function fileName(): string;

    abstract protected function directoryPath(): string;

    protected function filePath(): string
    {
        return $this->directoryPath().'/'.$this->fileName();
    }
}
