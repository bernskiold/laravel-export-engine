<?php

namespace Bernskiold\ExportEngine\Enums;

/**
 * Where the value of an export field is kept on the export.
 */
enum ExportFieldType: string
{
    case Filter = 'filter';

    case Option = 'option';
}
