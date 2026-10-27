<?php

use Bernskiold\ExportEngine\Data\ExportFieldDefinition;
use Bernskiold\ExportEngine\Data\ExportFields;
use Bernskiold\ExportEngine\Enums\ExportFieldValueType;
use Bernskiold\ExportEngine\Models\Export;
use Illuminate\Support\Facades\DB;

it('creates an instance with definitions', function () {
    $fields = ExportFields::make([
        ExportFieldDefinition::filter('countries', 'Countries'),
        ExportFieldDefinition::option('include_rankings', 'Include Rankings'),
    ]);

    expect($fields->definitions())->toHaveCount(2);
});

it('filters by field type', function () {
    $fields = ExportFields::make([
        ExportFieldDefinition::filter('countries', 'Countries'),
        ExportFieldDefinition::filter('brands', 'Brands'),
        ExportFieldDefinition::option('include_rankings', 'Include Rankings'),
    ]);

    expect($fields->filters())->toHaveCount(2);
    expect($fields->options())->toHaveCount(1);
});

it('can add definitions', function () {
    $fields = ExportFields::make([]);
    $fields->add(ExportFieldDefinition::filter('countries', 'Countries'));

    expect($fields->definitions())->toHaveCount(1);
});

it('can merge with another ExportFields instance', function () {
    $fields1 = ExportFields::make([
        ExportFieldDefinition::filter('countries', 'Countries'),
    ]);

    $fields2 = ExportFields::make([
        ExportFieldDefinition::filter('brands', 'Brands'),
    ]);

    $fields1->merge($fields2);

    expect($fields1->definitions())->toHaveCount(2);
});

it('resolves fields for export without model lookups', function () {
    $export = new Export([
        'filters' => [
            'search' => 'test query',
            'enabled' => true,
        ],
        'options' => [
            'include_rankings' => true,
        ],
    ]);

    $fields = ExportFields::make([
        ExportFieldDefinition::filter(
            key: 'search',
            label: 'Search',
            valueType: ExportFieldValueType::Text,
        ),
        ExportFieldDefinition::filter(
            key: 'enabled',
            label: 'Enabled',
            valueType: ExportFieldValueType::Boolean,
        ),
        ExportFieldDefinition::option(
            key: 'include_rankings',
            label: 'Include Rankings',
            valueType: ExportFieldValueType::Boolean,
        ),
    ]);

    $resolved = $fields->resolveForExport($export);

    expect($resolved)->toHaveCount(3);
    expect($resolved[0])->toMatchArray([
        'key' => 'search',
        'label' => 'Search',
        'values' => ['test query'],
        'type' => 'filter',
    ]);
    expect($resolved[1])->toMatchArray([
        'key' => 'enabled',
        'label' => 'Enabled',
        'values' => ['Yes'],
        'type' => 'filter',
    ]);
    expect($resolved[2])->toMatchArray([
        'key' => 'include_rankings',
        'label' => 'Include Rankings',
        'values' => ['Yes'],
        'type' => 'option',
    ]);
});

it('skips null and empty values', function () {
    $export = new Export([
        'filters' => [
            'search' => null,
            'countries' => [],
            'valid' => 'test',
        ],
        'options' => [],
    ]);

    $fields = ExportFields::make([
        ExportFieldDefinition::filter(
            key: 'search',
            label: 'Search',
            valueType: ExportFieldValueType::Text,
        ),
        ExportFieldDefinition::filter(
            key: 'countries',
            label: 'Countries',
            valueType: ExportFieldValueType::Multiselect,
        ),
        ExportFieldDefinition::filter(
            key: 'valid',
            label: 'Valid',
            valueType: ExportFieldValueType::Text,
        ),
    ]);

    $resolved = $fields->resolveForExport($export);

    expect($resolved)->toHaveCount(1);
    expect($resolved[0]->key)->toBe('valid');
});

it('resolves nested paths', function () {
    $export = new Export([
        'filters' => [
            'filters' => [
                'countries' => [1, 2, 3],
            ],
            'marketName' => 'Test Market',
        ],
        'options' => [],
    ]);

    $fields = ExportFields::make([
        ExportFieldDefinition::filter(
            key: 'countries',
            label: 'Countries',
            valueType: ExportFieldValueType::Text, // Simplified without model lookup
            path: 'filters.filters.countries',
        ),
        ExportFieldDefinition::filter(
            key: 'marketName',
            label: 'Market',
            valueType: ExportFieldValueType::Text,
            path: 'filters.marketName',
        ),
    ]);

    $resolved = $fields->resolveForExport($export);

    expect($resolved)->toHaveCount(2);
    expect($resolved[0])->toMatchArray([
        'key' => 'countries',
        'label' => 'Countries',
        'values' => [1, 2, 3],
        'type' => 'filter',
    ]);
    expect($resolved[1])->toMatchArray([
        'key' => 'marketName',
        'label' => 'Market',
        'values' => ['Test Market'],
        'type' => 'filter',
    ]);
});

it('gives the first value and the number of other values', function () {
    $field = new Bernskiold\ExportEngine\Data\ResolvedExportField('brands', 'Brands', ['Nike', 'Adidas', 'Puma']);

    expect($field->firstValue())->toBe('Nike')
        ->and($field->otherValuesCount())->toBe(2)
        ->and((new Bernskiold\ExportEngine\Data\ResolvedExportField('brands', 'Brands', []))->firstValue())->toBeNull();
});

it('finds the labels of models in one query for many exports', function () {
    $ada = makeUser('Ada');
    $grace = makeUser('Grace');

    $exports = collect([
        Export::generate('One.csv', 'csv', exporter: ExporterWithFieldsForFieldsTest::class, filters: ['people' => [$ada->id]]),
        Export::generate('Two.csv', 'csv', exporter: ExporterWithFieldsForFieldsTest::class, filters: ['people' => [$ada->id, $grace->id]]),
        Export::generate('Three.csv', 'csv', exporter: null, filters: ['people' => [$grace->id]]),
    ]);

    DB::enableQueryLog();

    $labels = ExportFields::resolveForExports($exports);

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and($labels[$exports[0]->id][0]->values)->toBe(['Ada'])
        ->and($labels[$exports[1]->id][0]->values)->toBe(['Ada', 'Grace'])
        ->and($labels[$exports[2]->id])->toBe([]);
});

it('describes the filters of one export from its exporter', function () {
    $export = Export::generate('One.csv', 'csv', exporter: ExporterWithFieldsForFieldsTest::class, filters: ['people' => [makeUser('Ada')->id]]);

    expect(collect($export->filterLabels())->map->toArray()->all())->toBe([[
        'key' => 'people',
        'label' => 'People',
        'values' => ['Ada'],
        'type' => 'filter',
        'displayPrefix' => null,
        'displaySuffix' => null,
    ]]);
});

class ExporterWithFieldsForFieldsTest extends Bernskiold\ExportEngine\SpreadsheetExporter implements Bernskiold\ExportEngine\Contracts\DefinesExportFields
{
    public static function fieldDefinitions(): ExportFields
    {
        return ExportFields::make([
            ExportFieldDefinition::filter('people', 'People', modelClass: Bernskiold\ExportEngine\Tests\Fixtures\User::class),
        ]);
    }

    protected function data(Spatie\SimpleExcel\SimpleExcelWriter $writer): void
    {
        //
    }
}
