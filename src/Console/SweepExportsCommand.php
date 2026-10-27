<?php

namespace Bernskiold\ExportEngine\Console;

use Bernskiold\ExportEngine\ExportEngine;
use Bernskiold\ExportEngine\Models\Export;
use Bernskiold\ExportEngine\Support\Staging;
use Illuminate\Console\Command;

/**
 * Removes the files that exports do not need. Schedule it each hour.
 *
 * - It expires the exports that are past their expiry time, and deletes
 *   their files.
 * - It fails the exports that stayed pending too long, because a worker
 *   stopped during the export.
 * - It deletes the files of failed exports.
 * - It deletes old files from the staging disk. Only a stopped export
 *   leaves a file there.
 */
class SweepExportsCommand extends Command
{
    protected $signature = 'exports:sweep';

    protected $description = 'Expire old exports, fail abandoned exports and delete the files they leave';

    public function handle(): int
    {
        $model = ExportEngine::model();

        $expired = 0;
        $model::query()->pastExpiry()->whereNotNull('file')->eachById(function (Export $export) use (&$expired) {
            $export->expire();
            $expired++;
        });

        $abandoned = 0;
        $model::query()->abandoned()->eachById(function (Export $export) use (&$abandoned) {
            $export->fail(__('The export did not finish. Please try again.'));
            $abandoned++;
        });

        $failedFiles = 0;
        $model::query()->failed()->whereNotNull('file')->eachById(function (Export $export) use (&$failedFiles) {
            $export->deleteFile();
            $failedFiles++;
        });

        $stagedFiles = $this->deleteOldStagedFiles();

        info("Expired {$expired}, failed {$abandoned} abandoned, deleted {$failedFiles} failed and {$stagedFiles} staged files.");

        return self::SUCCESS;
    }

    /**
     * The sweep does not look at the root of the export disk, because there
     * it can delete the files of available exports.
     */
    protected function deleteOldStagedFiles(): int
    {
        if (Staging::directory() === '' && config('export-engine.staging.disk') === config('export-engine.disk')) {
            return 0;
        }

        $disk = Staging::disk();
        $before = now()->subHours((int) config('export-engine.staging_files_after_hours', 24))->getTimestamp();
        $deleted = 0;

        foreach ($disk->allFiles(Staging::directory()) as $file) {
            if ($disk->lastModified($file) < $before) {
                $disk->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}
