<?php

namespace Bernskiold\ExportEngine\Enums;

/**
 * Where an export is in its life.
 *
 * The values are integers so that a table from an earlier version of the
 * engine keeps its data. Do not change them.
 */
enum ExportStatus: int
{
    case Requested = 0;

    case Generating = 1;

    case Available = 2;

    case Expired = 3;

    case Failed = 4;

    /**
     * The statuses of an export that has not finished yet.
     *
     * @return list<self>
     */
    public static function pending(): array
    {
        return [self::Requested, self::Generating];
    }

    public function isPending(): bool
    {
        return in_array($this, self::pending(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Requested => __('Requested'),
            self::Generating => __('Generating'),
            self::Available => __('Available'),
            self::Expired => __('Expired'),
            self::Failed => __('Failed'),
        };
    }
}
