<?php

namespace Bernskiold\ExportEngine\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Stringable;

/**
 * The name of a downloaded file. It tells what the export is, its filters
 * and when the engine made it, for example "Brand Overview - Nike, Adidas +3
 * - UK 2025 - 2026-10-21 14-03.xlsx". Thus a person can find an export in a
 * folder without opening the files.
 *
 * Give the filter labels with the most important first. When the name is
 * too long, the engine removes the least important labels. Then it cuts the
 * last label that starts in the name. The title and the date always stay.
 *
 * Excel sets the maximum length, not the file system. Excel does not open
 * a workbook with a full path of more than 218 characters. On Windows, the
 * Downloads folder uses approximately 40 of these characters.
 */
final class ExportFileName implements Stringable
{
    public const MAX_LENGTH = 150;

    /**
     * File systems count bytes, not characters. A script with three bytes
     * for each character fills the name before it looks long.
     */
    public const MAX_BYTES = 240;

    /**
     * The space for an extension, when the caller gives no extension. For
     * example, a Highcharts image adds its own extension. Thus the image and
     * the spreadsheet of the same view get the same name.
     */
    protected const RESERVED_EXTENSION_LENGTH = 5;

    protected const SEPARATOR = ' - ';

    protected const ELLIPSIS = '…';

    /**
     * A label shorter than this, after the cut, does not help. The engine
     * removes it.
     */
    protected const MIN_TRUNCATED_LABEL_LENGTH = 12;

    /**
     * @param  list<string>  $labels
     */
    public function __construct(
        public readonly string $title,
        public readonly array $labels = [],
        public readonly ?CarbonInterface $date = null,
    ) {}

    /**
     * @param  iterable<int, string|null>  $labels
     */
    public static function make(string $title, iterable $labels = [], ?CarbonInterface $date = null): self
    {
        return new self(
            title: self::titleFrom($title),
            labels: collect($labels)
                ->map(fn (?string $label) => self::sanitize((string) $label))
                ->filter(fn (string $label) => $label !== '')
                ->values()
                ->all(),
            date: $date,
        );
    }

    /**
     * The first names of a filter and the number of other names, for
     * example "Nike, Adidas +3". A file name cannot hold a long list.
     *
     * @param  iterable<array-key, string|int|null>  $names
     */
    public static function list(iterable $names, int $show = 2, string $glue = ', '): string
    {
        /** @var Collection<int, string> $names */
        $names = collect($names)
            ->map(fn (string|int|null $name) => trim((string) $name))
            ->filter(fn (string $name) => $name !== '')
            ->unique()
            ->values();

        $shown = $names->take($show)->implode($glue);

        if ($names->count() <= $show) {
            return $shown;
        }

        return $shown.' +'.($names->count() - $show);
    }

    public function toString(?string $extension = null): string
    {
        $date = ($this->date ?? now())->format('Y-m-d H-i');
        $suffix = self::SEPARATOR.$date.($extension ? '.'.$extension : '');

        $reserved = mb_strlen($suffix) + ($extension ? 0 : self::RESERVED_EXTENSION_LENGTH);
        $characters = self::MAX_LENGTH - $reserved;
        $bytes = self::MAX_BYTES - strlen($suffix) - ($extension ? 0 : self::RESERVED_EXTENSION_LENGTH);

        $name = $this->truncate($this->title !== '' ? $this->title : __('Export'), $characters, $bytes);

        foreach ($this->labels as $label) {
            $candidate = $name.self::SEPARATOR.$label;

            if ($this->fits($candidate, $characters, $bytes)) {
                $name = $candidate;

                continue;
            }

            $room = $characters - mb_strlen($name.self::SEPARATOR);

            if ($room >= self::MIN_TRUNCATED_LABEL_LENGTH) {
                $name = $this->truncate($candidate, $characters, $bytes);
            }

            break;
        }

        return $name.$suffix;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Some callers give a slug, for example "brand-overview". The engine
     * changes a slug into words. A title in words does not change.
     */
    protected static function titleFrom(string $title): string
    {
        $title = trim($title);

        if (preg_match('/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $title) !== 1) {
            return self::sanitize($title);
        }

        return collect(preg_split('/[-_]/', $title))
            ->map(fn (string $word) => self::acronyms()[$word] ?? str($word)->ucfirst()->toString())
            ->implode(' ');
    }

    /**
     * Removes the characters that a file system or the Content-Disposition
     * header does not accept. A slash is not permitted on disk. Also,
     * Symfony does not make a download header with a slash, so "AC/DC" can
     * stop the download.
     */
    protected static function sanitize(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        $value = preg_replace('/[\p{Cc}\p{Cf}]+/u', '', $value) ?? '';
        $value = preg_replace('/\s*[\/\\\\|]\s*/u', '-', $value) ?? '';
        $value = preg_replace('/\s*:\s*/u', ' - ', $value) ?? '';
        $value = str_replace('"', "'", $value);
        $value = preg_replace('/[*?<>]/u', '', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return trim($value, " .-\t");
    }

    /**
     * The words that stay in capitals when a slug changes into words, keyed
     * by the word in lower case.
     *
     * @return array<string, string>
     */
    protected static function acronyms(): array
    {
        $acronyms = [];

        foreach ((array) config('export-engine.file_names.acronyms', []) as $acronym) {
            $acronyms[mb_strtolower((string) $acronym)] = (string) $acronym;
        }

        return $acronyms;
    }

    protected function fits(string $value, int $characters, int $bytes): bool
    {
        return mb_strlen($value) <= $characters && strlen($value) <= $bytes;
    }

    protected function truncate(string $value, int $characters, int $bytes): string
    {
        if ($this->fits($value, $characters, $bytes)) {
            return $value;
        }

        $length = $characters - mb_strlen(self::ELLIPSIS);

        do {
            $cut = trim(mb_substr($value, 0, $length), " ,.-+\t").self::ELLIPSIS;
            $length--;
        } while ($length > 0 && ! $this->fits($cut, $characters, $bytes));

        return $cut;
    }
}
