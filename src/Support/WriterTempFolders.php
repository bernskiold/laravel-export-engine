<?php

namespace Bernskiold\ExportEngine\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * A temporary folder for each spreadsheet writer.
 *
 * OpenSpout builds an Excel file in a temporary folder and deletes the
 * folder only when the writer closes. A writer that fails, times out or
 * stops never closes, so its folder stays. Each writer of the engine gets
 * its own folder, and the engine deletes that folder when the writer is
 * done, whether it closed or not.
 *
 * The engine also deletes the open folders when a queued job times out or
 * PHP stops with a fatal error. Only a process that is killed from outside
 * leaves a folder. Delete old folders in the directory on a schedule.
 */
class WriterTempFolders
{
    /**
     * The folders that this process opened and did not delete yet.
     *
     * @var array<string, true>
     */
    protected static array $open = [];

    protected static bool $deletesOnShutdown = false;

    /**
     * The directory of the temporary folders, without a slash at the end.
     */
    public static function directory(): string
    {
        $directory = config('export-engine.spreadsheets.temp_directory');

        return rtrim(filled($directory) ? (string) $directory : sys_get_temp_dir(), '/');
    }

    /**
     * Make a new, empty folder for one writer.
     */
    public static function create(): string
    {
        $path = static::directory().'/export-engine-'.Str::uuid();

        File::ensureDirectoryExists($path);

        static::$open[$path] = true;

        if (! static::$deletesOnShutdown) {
            register_shutdown_function(static::deleteAll(...));
            static::$deletesOnShutdown = true;
        }

        return $path;
    }

    public static function delete(string $path): void
    {
        File::deleteDirectory($path);

        unset(static::$open[$path]);
    }

    /**
     * Delete each folder this process opened. The writers that use them
     * cannot finish after this, so do this only when the process stops.
     */
    public static function deleteAll(): void
    {
        foreach (array_keys(static::$open) as $path) {
            static::delete($path);
        }
    }
}
