<?php

namespace Bernskiold\ExportEngine\Events;

use Bernskiold\ExportEngine\Models\Export;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An export is complete and its file is on the export disk.
 */
class ExportFinished
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Export $export,
    ) {}
}
