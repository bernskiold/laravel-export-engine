<?php

namespace Bernskiold\ExportEngine\Tests\Fixtures;

use Bernskiold\ExportEngine\Contracts\Exporter;
use Bernskiold\ExportEngine\Data\ExportedFile;
use Bernskiold\ExportEngine\Models\Export;

/**
 * Writes a text file, with no spreadsheet writer. It is like a deck or any
 * other file an application makes itself.
 */
class TextExporter implements Exporter
{
    public static ?string $wrote = null;

    public function write(Export $export, string $path): ExportedFile
    {
        file_put_contents("{$path}.txt", $export->option('text', 'Hello'));

        static::$wrote = app()->getLocale();

        return ExportedFile::at("{$path}.txt", notes: $export->option('notes', []));
    }
}
