<?php

namespace Bernskiold\ExportEngine\Support;

use Bernskiold\ExportEngine\Exceptions\ExportException;
use Bernskiold\ExportEngine\Exceptions\ExportTooLarge;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * A writer that stops an export before it fills the disk.
 *
 * It throws when the file gets more rows than the limit, and it checks the
 * free disk space every few thousand rows. An exporter that streams its
 * rows has no other limit: OpenSpout starts a new sheet each time a sheet
 * is full, and a large Excel file needs many times its size on disk while
 * the writer builds it.
 */
class GuardedWriter extends SimpleExcelWriter
{
    public const int DISK_CHECK_EVERY_ROWS = 10_000;

    protected ?int $maxRows = null;

    protected ?int $minFreeBytes = null;

    /**
     * @var array<int, string>
     */
    protected array $watchedDirectories = [];

    /**
     * Throw when the file would get more than $maxRows rows, the header
     * included. Null means no limit.
     */
    public function limitRows(?int $maxRows): static
    {
        $this->maxRows = $maxRows;

        return $this;
    }

    /**
     * Throw when a directory has less than $megabytes free. Null means no
     * check.
     *
     * @param  array<int, string|null>  $directories
     */
    public function stopBelowFreeSpace(?int $megabytes, array $directories): static
    {
        $this->minFreeBytes = $megabytes === null ? null : $megabytes * 1024 * 1024;
        $this->watchedDirectories = array_values(array_unique(array_filter($directories)));

        return $this;
    }

    /**
     * @param  Row|array<array-key, mixed>  $row
     */
    public function addRow(Row|array $row, ?Style $style = null): static
    {
        $written = $this->getWriter()->getWrittenRowCount();

        if ($this->maxRows !== null && $written >= $this->maxRows) {
            throw ExportTooLarge::rows($this->maxRows);
        }

        if ($written > 0 && $written % self::DISK_CHECK_EVERY_ROWS === 0) {
            $this->ensureFreeSpace();
        }

        return parent::addRow($row, $style);
    }

    public function ensureFreeSpace(): void
    {
        if ($this->minFreeBytes === null) {
            return;
        }

        foreach ($this->watchedDirectories as $directory) {
            $free = @disk_free_space($directory);

            if ($free !== false && $free < $this->minFreeBytes) {
                throw new ExportException(sprintf(
                    'Stopped the export: only %d MB is free on the disk of %s, and the limit is %d MB.',
                    intdiv((int) $free, 1024 * 1024),
                    $directory,
                    intdiv($this->minFreeBytes, 1024 * 1024),
                ));
            }
        }
    }
}
