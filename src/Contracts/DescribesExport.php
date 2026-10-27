<?php

namespace Bernskiold\ExportEngine\Contracts;

/**
 * An exporter that gives its name for a person, for example in a list of
 * exports or in a notification.
 */
interface DescribesExport
{
    /**
     * What the export is, for example "Sales report".
     */
    public static function exportLabel(): string;
}
