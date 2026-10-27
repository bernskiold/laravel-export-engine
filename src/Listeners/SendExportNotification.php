<?php

namespace Bernskiold\ExportEngine\Listeners;

use Bernskiold\ExportEngine\Events\ExportFailed;
use Bernskiold\ExportEngine\Events\ExportFinished;

/**
 * Tells the person who asked for a queued export that it is ready, or that
 * it failed. The notifications come from the config.
 */
class SendExportNotification
{
    public function handle(ExportFinished|ExportFailed $event): void
    {
        $export = $event->export;

        if (! $export->is_queued) {
            return;
        }

        $notification = config($event instanceof ExportFinished
            ? 'export-engine.notifications.finished'
            : 'export-engine.notifications.failed');

        $user = $export->user;

        if ($notification === null || $user === null || ! method_exists($user, 'notify')) {
            return;
        }

        $user->notify(new $notification($export));
    }
}
