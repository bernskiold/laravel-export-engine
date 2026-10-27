<?php

namespace Bernskiold\ExportEngine\Tests\Fixtures;

use Bernskiold\ExportEngine\Contracts\WithHeader;
use Bernskiold\ExportEngine\SpreadsheetExporter;
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * Writes one row for each brand in the "brands" filter.
 */
class BrandsExporter extends SpreadsheetExporter implements WithHeader
{
    public function header(): array
    {
        return ['Brand Name', 'Rank'];
    }

    protected function data(SimpleExcelWriter $writer): void
    {
        foreach ($this->export->filter('brands') as $rank => $brand) {
            $writer->addRow(['Brand Name' => $brand, 'Rank' => $rank + 1]);
        }
    }
}
