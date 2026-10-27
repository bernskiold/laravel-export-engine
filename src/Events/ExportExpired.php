<?php

namespace Bernskiold\ExportEngine\Events;

use Bernskiold\ExportEngine\Models\Export;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The file of an export expired and the engine deleted it.
 */
class ExportExpired
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Export $export,
    ) {}
}
