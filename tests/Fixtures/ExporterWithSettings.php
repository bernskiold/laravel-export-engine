<?php

namespace Bernskiold\ExportEngine\Tests\Fixtures;

use Bernskiold\ExportEngine\Contracts\HasExportSettings;
use Bernskiold\ExportEngine\Contracts\WithHeader;
use Bernskiold\ExportEngine\Settings\ChoiceSetting;
use Bernskiold\ExportEngine\Settings\ExportSettingsContext;
use Bernskiold\ExportEngine\Settings\MultiChoiceSetting;
use Bernskiold\ExportEngine\Settings\NumberSetting;
use Bernskiold\ExportEngine\Settings\ToggleSetting;
use Bernskiold\ExportEngine\SpreadsheetExporter;
use Spatie\SimpleExcel\SimpleExcelWriter;

class ExporterWithSettings extends SpreadsheetExporter implements HasExportSettings, WithHeader
{
    public static function exportSettings(ExportSettingsContext $context): array
    {
        return [
            ChoiceSetting::make('sheets', 'Sheets')->options(SheetLayout::class)->default(SheetLayout::Combined),
            ChoiceSetting::make('order', 'Order')->options(['name' => 'Name', 'rank' => 'Rank'])->dropdown()->default('name'),
            ChoiceSetting::make('size', 'Size')->options(['small' => 'Small', 'large' => 'Large'])->segmented()->default('small'),
            MultiChoiceSetting::make('columns', 'Columns')->options(['name' => 'Name', 'rank' => 'Rank']),
            NumberSetting::make('limit', 'Limit')->between(1, 100)->default(10),
            ToggleSetting::make('totals', 'Totals'),
        ];
    }

    public function header(): array
    {
        return ['Sheets', 'Limit', 'Totals'];
    }

    protected function data(SimpleExcelWriter $writer): void
    {
        $writer->addRow([
            'Sheets' => $this->setting('sheets')->value,
            'Limit' => $this->setting('limit'),
            'Totals' => $this->setting('totals') ? 'yes' : 'no',
        ]);
    }
}
