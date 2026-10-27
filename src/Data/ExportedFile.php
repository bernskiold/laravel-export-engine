<?php

namespace Bernskiold\ExportEngine\Data;

/**
 * The file an exporter wrote.
 */
class ExportedFile
{
    /**
     * @param  string  $path  The local path of the file, with its extension.
     * @param  list<string>  $notes  Sentences for the person who asked, for example "Coca-Cola is not included".
     */
    public function __construct(
        public readonly string $path,
        public readonly array $notes = [],
    ) {}

    /**
     * @param  list<string>  $notes
     */
    public static function at(string $path, array $notes = []): self
    {
        return new self($path, $notes);
    }
}
