<?php

namespace Bernskiold\ExportEngine\Support;

/**
 * Gives sheet names that Excel accepts, for one workbook.
 *
 * A sheet name has a maximum of 31 characters. It cannot contain
 * `\ / ? * [ ] :`, and it must be unique. Two long names that start with the
 * same words are not unique after the cut, so the class adds a number.
 */
class SheetNames
{
    protected const MaxLength = 31;

    /**
     * @var array<int, string>
     */
    protected array $taken = [];

    public function for(string $name): string
    {
        $name = trim(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $name), " '");

        if ($name === '') {
            $name = __('Sheet');
        }

        $candidate = mb_substr($name, 0, self::MaxLength);
        $suffix = 1;

        while (in_array(mb_strtolower($candidate), $this->taken, true)) {
            $suffix++;
            $ending = " ({$suffix})";
            $candidate = mb_substr($name, 0, self::MaxLength - mb_strlen($ending)).$ending;
        }

        $this->taken[] = mb_strtolower($candidate);

        return $candidate;
    }
}
