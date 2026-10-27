<?php

namespace Bernskiold\ExportEngine\Contracts;

use Bernskiold\ExportEngine\Data\ExportedFile;
use Bernskiold\ExportEngine\Models\Export;

/**
 * Makes the file of an export.
 *
 * The engine makes the exporter from the container. It gives the export as
 * the `$export` constructor argument, so an exporter can type-hint its other
 * dependencies next to it.
 */
interface Exporter
{
    /**
     * Write the file of the export and tell the engine where it is.
     *
     * @param  string  $path  A local path for the file, without an extension. Its directory exists.
     */
    public function write(Export $export, string $path): ExportedFile;
}
