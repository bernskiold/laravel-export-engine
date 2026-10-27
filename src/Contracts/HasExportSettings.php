<?php

namespace Bernskiold\ExportEngine\Contracts;

use Bernskiold\ExportEngine\Settings\ExportSetting;
use Bernskiold\ExportEngine\Settings\ExportSettingsContext;

/**
 * An exporter or deck that asks the person how they want their file before
 * the engine makes it. The engine shows these settings before the export
 * starts, and the exporter reads the values while it runs.
 */
interface HasExportSettings
{
    /**
     * @return array<int, ExportSetting>
     */
    public static function exportSettings(ExportSettingsContext $context): array;
}
