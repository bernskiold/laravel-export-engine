<?php

namespace Bernskiold\ExportEngine\Contracts;

use Bernskiold\ExportEngine\Models\Export;

/**
 * An exporter that checks the person again when the export runs.
 *
 * A queued export can run a long time after the person asked for it. In
 * that time, they can lose their access. If the check fails, the export
 * fails with the reason below and the exporter does not run.
 */
interface AuthorizesExport
{
    public function authorize(Export $export): bool;

    /**
     * What the person sees when the check fails.
     */
    public function unauthorizedReason(Export $export): string;
}
