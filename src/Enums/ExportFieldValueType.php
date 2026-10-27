<?php

namespace Bernskiold\ExportEngine\Enums;

/**
 * The type of value an export field holds, which sets how its label is made.
 */
enum ExportFieldValueType: string
{
    case Multiselect = 'multiselect';

    case Boolean = 'boolean';

    case Date = 'date';

    case Text = 'text';

    case Enum = 'enum';
}
