<?php

namespace Bernskiold\ExportEngine\Data;

use Bernskiold\ExportEngine\Enums\ExportFieldType;
use Bernskiold\ExportEngine\Enums\ExportFieldValueType;
use Closure;

/**
 * One filter or option of an exporter, and how to make a label from its
 * value. A field with a model class gets the labels from that model.
 */
class ExportFieldDefinition
{
    /**
     * @param  class-string|null  $modelClass
     */
    public function __construct(
        public string $key,
        public string $label,
        public ExportFieldType $fieldType,
        public ExportFieldValueType $valueType = ExportFieldValueType::Text,
        public ?string $modelClass = null,
        public string $labelAttribute = 'name',
        public ?Closure $resolver = null,
        public ?string $path = null,
        public ?string $displayPrefix = null,
        public ?string $displaySuffix = null,
    ) {
        $this->path ??= $this->fieldType === ExportFieldType::Filter
            ? "filters.{$this->key}"
            : "options.{$this->key}";
    }

    /**
     * @param  class-string|null  $modelClass
     */
    public static function filter(
        string $key,
        string $label,
        ExportFieldValueType $valueType = ExportFieldValueType::Multiselect,
        ?string $modelClass = null,
        string $labelAttribute = 'name',
        ?Closure $resolver = null,
        ?string $path = null,
        ?string $displayPrefix = null,
        ?string $displaySuffix = null,
    ): self {
        return new self(
            key: $key,
            label: $label,
            fieldType: ExportFieldType::Filter,
            valueType: $valueType,
            modelClass: $modelClass,
            labelAttribute: $labelAttribute,
            resolver: $resolver,
            path: $path,
            displayPrefix: $displayPrefix,
            displaySuffix: $displaySuffix,
        );
    }

    public static function option(
        string $key,
        string $label,
        ExportFieldValueType $valueType = ExportFieldValueType::Boolean,
        ?Closure $resolver = null,
        ?string $path = null,
        ?string $displayPrefix = null,
        ?string $displaySuffix = null,
    ): self {
        return new self(
            key: $key,
            label: $label,
            fieldType: ExportFieldType::Option,
            valueType: $valueType,
            resolver: $resolver,
            path: $path,
            displayPrefix: $displayPrefix,
            displaySuffix: $displaySuffix,
        );
    }

    /**
     * Whether the labels of this field come from a model.
     */
    public function resolvesModels(): bool
    {
        return $this->modelClass !== null && $this->valueType === ExportFieldValueType::Multiselect;
    }

    /**
     * The labels for a value. A field with a model class finds its models.
     *
     * @return array<int, string>
     */
    public function resolve(mixed $value): array
    {
        if ($value === null || $this->resolver !== null || ! $this->resolvesModels()) {
            return $this->resolveWithCache($value, []);
        }

        $ids = array_filter(is_array($value) ? $value : [$value]);

        if ($ids === []) {
            return [];
        }

        return $this->resolveWithCache($value, $this->modelClass::query()
            ->whereIn('id', $ids)
            ->pluck($this->labelAttribute, 'id')
            ->all());
    }

    /**
     * The labels for a value, with the labels of the models found before.
     *
     * @param  array<int|string, string>  $resolvedModels  The labels of the models, keyed by id.
     * @return array<int, string>
     */
    public function resolveWithCache(mixed $value, array $resolvedModels): array
    {
        if ($value === null) {
            return [];
        }

        if ($this->resolver !== null) {
            $result = ($this->resolver)($value);

            return is_array($result) ? $result : [$result];
        }

        if ($this->valueType === ExportFieldValueType::Boolean) {
            return [$value ? __('Yes') : __('No')];
        }

        if ($this->resolvesModels()) {
            return array_values(array_filter(array_map(
                fn ($id) => $resolvedModels[$id] ?? null,
                is_array($value) ? $value : [$value],
            )));
        }

        return is_array($value) ? $value : [(string) $value];
    }
}
