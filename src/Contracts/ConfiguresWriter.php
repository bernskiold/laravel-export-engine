<?php

namespace Bernskiold\ExportEngine\Contracts;

use OpenSpout\Writer\WriterInterface;

/**
 * A spreadsheet exporter that changes the OpenSpout writer before it writes
 * the first row.
 */
interface ConfiguresWriter
{
    public function writerOptions(WriterInterface $writer): void;
}
