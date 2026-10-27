<?php

namespace Bernskiold\ExportEngine\Data;

/**
 * The options a page gives to its export: the options of the exporter, and
 * the state of the page next to them.
 *
 * Add the state of the page when you start the export, not in the options
 * of the exporter. Then a component can change its options and still keep
 * the state of the page. An option that the exporter sets is stronger than
 * the state of the page. Only the listing replaces an option.
 *
 * Extend this class to add the state of your own pages.
 */
class ExportOptions
{
    /**
     * @param  array<string, mixed>  $options
     */
    final public function __construct(protected array $options = []) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public static function from(array $options): static
    {
        return new static($options);
    }

    /**
     * The listing of the table that starts the export. An exporter for more
     * than one table uses it to find the table.
     */
    public function withListing(?ListingFilters $listing): static
    {
        if ($listing !== null) {
            $this->options['listing'] = $listing::class;
        }

        return $this;
    }

    /**
     * Add an option when the exporter does not set it. An empty value adds
     * nothing.
     */
    public function withDefault(string $key, mixed $value): static
    {
        if (empty($value)) {
            return $this;
        }

        $this->options = [$key => $value, ...$this->options];

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->options;
    }
}
