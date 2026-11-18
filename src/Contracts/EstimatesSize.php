<?php

namespace Bernskiold\ExportEngine\Contracts;

/**
 * An exporter that can count its rows before it writes them.
 *
 * The engine asks before it starts an Excel export, and refuses the export
 * when it has more rows than `spreadsheets.max_rows`, or more cells than
 * `spreadsheets.max_cells`. The cells are the rows times the columns of the
 * header, so implement `WithHeader` too. The person sees why, and can make
 * the export smaller before it uses a worker for hours.
 *
 * An estimate is enough. Make it cheap: the person waits for it.
 */
interface EstimatesSize
{
    public function estimatedRows(): int;
}
