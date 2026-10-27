<?php

namespace Bernskiold\ExportEngine\Http\Controllers;

use Bernskiold\ExportEngine\ExportEngine;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sends the file of an export to the person who asked for it.
 */
class DownloadExportController
{
    public function __invoke(string $export): StreamedResponse
    {
        $export = ExportEngine::model()::query()->byUuid($export)->firstOrFail();

        Gate::authorize('download', $export);

        abort_unless($export->isDownloadable(), 404);

        $download = $export->download();

        if ($download === null) {
            abort(404);
        }

        $export->recordDownload();

        return $download;
    }
}
