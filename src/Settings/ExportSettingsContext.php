<?php

namespace Bernskiold\ExportEngine\Settings;

use Bernskiold\ExportEngine\Models\Export;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The data an export starts from. A setting can use it, for example to show
 * a choice of sheets only when the export has more than one study.
 */
class ExportSettingsContext
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public readonly string $format,
        public readonly array $filters = [],
        public readonly array $options = [],
        public readonly ?Authenticatable $user = null,
    ) {}

    public static function fromExport(Export $export): self
    {
        return new self(
            format: $export->format,
            filters: $export->filters ?? [],
            options: $export->options ?? [],
            user: $export->requester(),
        );
    }

    public function filter(string $key, mixed $default = null): mixed
    {
        return $this->filters[$key] ?? $default;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
