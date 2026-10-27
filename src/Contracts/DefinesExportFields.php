<?php

namespace Bernskiold\ExportEngine\Contracts;

use Bernskiold\ExportEngine\Data\ExportFields;

/**
 * An exporter that tells how to show its filters and options to a person,
 * for example in a list of their exports.
 */
interface DefinesExportFields
{
    public static function fieldDefinitions(): ExportFields;
}
