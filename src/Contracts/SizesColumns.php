<?php

namespace Bernskiold\ExportEngine\Contracts;

/**
 * A spreadsheet exporter that sets the width of its columns. Only Excel
 * files use this.
 */
interface SizesColumns
{
    /**
     * The widths of the columns, from the first column.
     *
     * @return array<int, float|int>
     */
    public function columnSizes(): array;
}
