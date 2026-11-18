<?php

namespace Bernskiold\ExportEngine\Exceptions;

/**
 * The export is too large to write as it is. The person sees the message
 * and can make the export smaller.
 */
class ExportTooLarge extends ExportNotAllowed
{
    public static function rows(int $maxRows): self
    {
        return new self(__('This export has more than :rows rows, which is more than an Excel file can hold. Select less data, or export it as CSV.', [
            'rows' => number_format($maxRows),
        ]));
    }

    public static function estimated(int $rows, int $columns): self
    {
        return new self(__('This export would have about :rows rows and :columns columns, which is too large for an Excel file. Select less data, or export it as CSV.', [
            'rows' => number_format($rows),
            'columns' => number_format($columns),
        ]));
    }
}
