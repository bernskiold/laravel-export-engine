<?php

namespace Bernskiold\ExportEngine\Events;

use Bernskiold\ExportEngine\Models\Export;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An export failed. Its failure_reason tells the person why.
 */
class ExportFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Export $export,
    ) {}
}
