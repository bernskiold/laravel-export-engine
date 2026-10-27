<?php

namespace Bernskiold\ExportEngine\Contracts;

/**
 * A spreadsheet exporter that writes a header row.
 */
interface WithHeader
{
    /**
     * @return array<int, string>
     */
    public function header(): array;
}
