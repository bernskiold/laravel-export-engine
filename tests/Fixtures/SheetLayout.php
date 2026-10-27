<?php

namespace Bernskiold\ExportEngine\Tests\Fixtures;

enum SheetLayout: string
{
    case Combined = 'combined';

    case PerStudy = 'per_study';

    case PerAudience = 'per_audience';

    public function label(): string
    {
        return match ($this) {
            self::Combined => 'All in one sheet',
            self::PerStudy => 'One sheet per study',
            self::PerAudience => 'One sheet per audience',
        };
    }
}
