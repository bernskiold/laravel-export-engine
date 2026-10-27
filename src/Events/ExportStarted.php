<?php

namespace Bernskiold\ExportEngine\Events;

use Bernskiold\ExportEngine\Models\Export;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The engine started an export, or started it again.
 */
class ExportStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Export $export,
    ) {}
}
