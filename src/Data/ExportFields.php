<?php

namespace Bernskiold\ExportEngine\Data;

use Bernskiold\ExportEngine\Contracts\DefinesExportFields;
use Bernskiold\ExportEngine\Enums\ExportFieldType;
use Bernskiold\ExportEngine\Models\Export;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * The fields of an exporter, which tell how to show the filters and options
 * of its exports to a person.
 */
class ExportFields
{
    /**
     * @param  Collection<int, ExportFieldDefinition>  $definitions
     */
    public function __construct(
        protected Collection $definitions,
    ) {}

    /**
     * @param  array<int, ExportFieldDefinition>  $definitions
     */
    public static function make(array $definitions = []): self
    {
        return new self(collect($definitions));
    }

    /**
     * @return Collection<int, ExportFieldDefinition>
     */
    public function definitions(): Collection
    {
        return $this->definitions;
    }

    /**
     * @return Collection<int, ExportFieldDefinition>
     */
    public function filters(): Collection
    {
        return $this->definitions->filter(fn (ExportFieldDefinition $definition) => $definition->fieldType === ExportFieldType::Filter);
    }

    /**
     * @return Collection<int, ExportFieldDefinition>
     */
    public function options(): Collection
    {
        return $this->definitions->filter(fn (ExportFieldDefinition $definition) => $definition->fieldType === ExportFieldType::Option);
    }

    public function add(ExportFieldDefinition $definition): self
    {
        $this->definitions->push($definition);

        return $this;
    }

    public function merge(self $other): self
    {
        $this->definitions = $this->definitions->merge($other->definitions());

        return $this;
    }

    /**
     * The labels of all the fields of an export. The engine finds the
     * models of each model class in one query.
     *
     * @return list<ResolvedExportField>
     */
    public function resolveForExport(Export $export): array
    {
        $data = static::dataOf($export);

        return $this->buildResolvedOutput($data, static::findModelLabels($this->modelIds($data), [$this]));
    }

    /**
     * The labels of many exports. The engine finds the models of each model
     * class in one query for all the exports. Use this for a list of
     * exports.
     *
     * @param  Collection<int, Export>  $exports
     * @return array<int|string, list<ResolvedExportField>> Keyed by the id of the export.
     */
    public static function resolveForExports(Collection $exports): array
    {
        $fields = [];
        $ids = [];

        foreach ($exports as $export) {
            $exporter = $export->exporterClass();

            if ($exporter === null || ! class_exists($exporter) || ! is_a($exporter, DefinesExportFields::class, true)) {
                continue;
            }

            $fields[$export->getKey()] = $exporter::fieldDefinitions();

            foreach ($fields[$export->getKey()]->modelIds(static::dataOf($export)) as $modelClass => $modelIds) {
                $ids[$modelClass] = array_merge($ids[$modelClass] ?? [], $modelIds);
            }
        }

        $labels = static::findModelLabels($ids, $fields);

        return $exports
            ->mapWithKeys(fn (Export $export) => [$export->getKey() => isset($fields[$export->getKey()])
                ? $fields[$export->getKey()]->buildResolvedOutput(static::dataOf($export), $labels)
                : $export->filterLabelsWithoutDefinitions()])
            ->all();
    }

    /**
     * The ids to find for each model class.
     *
     * @param  array<string, mixed>  $data
     * @return array<class-string, array<int, int|string>>
     */
    protected function modelIds(array $data): array
    {
        $ids = [];

        foreach ($this->definitions as $definition) {
            if (! $definition->resolvesModels()) {
                continue;
            }

            $value = Arr::get($data, $definition->path);

            $ids[$definition->modelClass] = array_merge(
                $ids[$definition->modelClass] ?? [],
                array_filter(is_array($value) ? $value : [$value]),
            );
        }

        return $ids;
    }

    /**
     * The labels of the models, keyed by model class and then by id.
     *
     * @param  array<class-string, array<int, int|string>>  $ids
     * @param  iterable<self>  $fields  The fields that tell which attribute is the label of each model class.
     * @return array<class-string, array<int|string, string>>
     */
    protected static function findModelLabels(array $ids, iterable $fields): array
    {
        $labels = [];

        foreach ($ids as $modelClass => $modelIds) {
            $modelIds = array_values(array_unique($modelIds));

            if ($modelIds === []) {
                continue;
            }

            $labels[$modelClass] = $modelClass::query()
                ->whereIn('id', $modelIds)
                ->pluck(static::labelAttributeFor($modelClass, $fields), 'id')
                ->all();
        }

        return $labels;
    }

    /**
     * @param  iterable<self>  $fields
     */
    protected static function labelAttributeFor(string $modelClass, iterable $fields): string
    {
        foreach ($fields as $field) {
            $definition = $field->definitions()->first(fn (ExportFieldDefinition $definition) => $definition->modelClass === $modelClass);

            if ($definition !== null) {
                return $definition->labelAttribute;
            }
        }

        return 'name';
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<class-string, array<int|string, string>>  $modelLabels
     * @return list<ResolvedExportField>
     */
    protected function buildResolvedOutput(array $data, array $modelLabels): array
    {
        $resolved = [];

        foreach ($this->definitions as $definition) {
            $value = Arr::get($data, $definition->path);

            if ($value === null || $value === []) {
                continue;
            }

            $labels = $definition->resolvesModels()
                ? $definition->resolveWithCache($value, $modelLabels[$definition->modelClass] ?? [])
                : $definition->resolve($value);

            if ($labels === []) {
                continue;
            }

            $resolved[] = new ResolvedExportField(
                key: $definition->key,
                label: $definition->label,
                values: array_values(array_map('strval', $labels)),
                type: $definition->fieldType,
                displayPrefix: $definition->displayPrefix,
                displaySuffix: $definition->displaySuffix,
            );
        }

        return $resolved;
    }

    /**
     * @return array{filters: array<string, mixed>, options: array<string, mixed>}
     */
    protected static function dataOf(Export $export): array
    {
        return [
            'filters' => $export->filters ?? [],
            'options' => $export->options ?? [],
        ];
    }
}
