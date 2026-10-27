<?php

namespace Bernskiold\ExportEngine\Exceptions;

use Bernskiold\ExportEngine\Models\Export;

/**
 * The exporter said it wrote a file, but the file is not there.
 */
class ExportNotWritten extends ExportException
{
    public static function at(Export $export, string $path): self
    {
        return new self("Export {$export->getKey()} did not write its file to {$path}.");
    }
}
