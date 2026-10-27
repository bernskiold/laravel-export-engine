<?php

namespace Bernskiold\ExportEngine\Exceptions;

/**
 * The person cannot have this export. Throw this from an exporter or from
 * middleware. The export fails and the person sees the message, so write
 * the message for them. The job does not try again.
 */
class ExportNotAllowed extends ExportException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
