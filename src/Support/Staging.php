<?php

namespace Bernskiold\ExportEngine\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * The local disk where exporters write their files before the engine moves
 * them to the export disk.
 */
class Staging
{
    public static function disk(): Filesystem
    {
        return Storage::disk(config('export-engine.staging.disk', 'local'));
    }

    /**
     * The directory on the staging disk, relative to its root.
     */
    public static function directory(): string
    {
        return trim((string) config('export-engine.staging.directory', ''), '/');
    }

    /**
     * The full local path of the staging directory, without a slash at the end.
     */
    public static function root(): string
    {
        return rtrim(static::disk()->path(static::directory()), '/');
    }

    /**
     * The full local path for a path relative to the staging directory.
     */
    public static function path(string $relativePath): string
    {
        return static::root().'/'.ltrim($relativePath, '/');
    }

    /**
     * The path to keep a staged file under on the export disk. A file in
     * the staging directory keeps its path in that directory. A file
     * somewhere else goes into the directory given.
     */
    public static function relativePath(string $localPath, string $fallbackDirectory): string
    {
        $root = static::root().'/';

        if (str_starts_with($localPath, $root)) {
            return substr($localPath, strlen($root));
        }

        return trim($fallbackDirectory, '/').'/'.basename($localPath);
    }
}
