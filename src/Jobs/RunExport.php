<?php

namespace Bernskiold\ExportEngine\Jobs;

use Bernskiold\ExportEngine\Support\ExportRunner;

/**
 * Runs an export with its exporter. This is the job of every export that
 * does not name a job of its own.
 */
class RunExport extends ExportJob
{
    public function process(): void
    {
        app(ExportRunner::class)->run($this->export);
    }
}
