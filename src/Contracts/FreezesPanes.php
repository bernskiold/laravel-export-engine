<?php

namespace Bernskiold\ExportEngine\Contracts;

/**
 * A spreadsheet exporter that freezes rows and columns, so they stay on the
 * screen when the person scrolls. Only Excel files use this.
 */
interface FreezesPanes
{
    /**
     * The first column that scrolls, as a letter. "B" freezes column A.
     */
    public function freezeColumn(): string;

    /**
     * The first row that scrolls. 2 freezes row 1.
     */
    public function freezeRow(): int;
}
