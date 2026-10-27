<?php

namespace Bernskiold\ExportEngine\Data;

use Bernskiold\ExportEngine\Enums\ExportFieldType;
use Illuminate\Contracts\Support\Arrayable;

/**
 * One filter or option of an export, with its labels for a person.
 *
 * @implements Arrayable<string, mixed>
 */
class ResolvedExportField implements Arrayable
{
    /**
     * @param  list<string>  $values  The labels of the values, for example the names of the brands.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $values,
        public readonly ExportFieldType $type = ExportFieldType::Filter,
        public readonly ?string $displayPrefix = null,
        public readonly ?string $displaySuffix = null,
    ) {}

    /**
     * The first value, or null when there are no values.
     */
    public function firstValue(): ?string
    {
        return $this->values[0] ?? null;
    }

    /**
     * The number of values after the first value.
     */
    public function otherValuesCount(): int
    {
        return max(count($this->values) - 1, 0);
    }

    /**
     * @return array{key: string, label: string, values: list<string>, type: string, displayPrefix: string|null, displaySuffix: string|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'values' => $this->values,
            'type' => $this->type->value,
            'displayPrefix' => $this->displayPrefix,
            'displaySuffix' => $this->displaySuffix,
        ];
    }
}
