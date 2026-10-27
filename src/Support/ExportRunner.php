<?php

namespace Bernskiold\ExportEngine\Support;

use Bernskiold\ExportEngine\Contracts\AuthorizesExport;
use Bernskiold\ExportEngine\Contracts\Exporter;
use Bernskiold\ExportEngine\Exceptions\ExportException;
use Bernskiold\ExportEngine\Exceptions\ExportNotWritten;
use Bernskiold\ExportEngine\ExportEngine;
use Bernskiold\ExportEngine\Models\Export;
use Illuminate\Support\Facades\File;
use ReflectionClass;

/**
 * Runs the exporter of an export: it checks the person, gets the exporter
 * to write the file on the staging disk, and moves the file to the export
 * disk.
 */
class ExportRunner
{
    public function run(Export $export, ?Exporter $exporter = null): Export
    {
        $exporter ??= $this->exporterFor($export);

        if (! ExportEngine::canExport($export->requester(), $export->exporterClass())) {
            return $export->fail(__('You no longer have access to this export.'));
        }

        if ($exporter instanceof AuthorizesExport && ! $exporter->authorize($export)) {
            return $export->fail($exporter->unauthorizedReason($export));
        }

        $directory = static::directoryFor($exporter);
        $path = Staging::path("{$directory}/{$export->uuid}");

        File::ensureDirectoryExists(dirname($path));

        $file = $exporter->write($export, $path);

        if (! is_file($file->path)) {
            throw ExportNotWritten::at($export, $file->path);
        }

        return $export->storeExportedFile($file, Staging::relativePath($file->path, $directory));
    }

    public function exporterFor(Export $export): Exporter
    {
        $class = $export->exporterClass();

        if (! ExportEngine::isExporter($class)) {
            throw new ExportException("Export {$export->getKey()} has no exporter that the engine can run.");
        }

        return app()->make($class, ['export' => $export]);
    }

    /**
     * The directory for the files of an exporter: its permanent name, or its
     * class name in kebab case. An anonymous class has no name to use.
     */
    public static function directoryFor(Exporter|string $exporter): string
    {
        $class = is_string($exporter) ? $exporter : $exporter::class;
        $alias = ExportEngine::exporterAlias($class);

        if ($alias !== $class) {
            return str($alias)->slug()->toString();
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->isAnonymous()) {
            return 'exports';
        }

        return str($reflection->getShortName())->snake('-')->toString();
    }
}
