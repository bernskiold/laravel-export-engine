<?php

use Bernskiold\ExportEngine\Data\ExportFieldDefinition;
use Bernskiold\ExportEngine\Enums\ExportFieldType;
use Bernskiold\ExportEngine\Enums\ExportFieldValueType;

it('creates a filter field definition with default path', function () {
    $definition = ExportFieldDefinition::filter(
        key: 'countries',
        label: 'Countries',
    );

    expect($definition->key)->toBe('countries');
    expect($definition->label)->toBe('Countries');
    expect($definition->fieldType)->toBe(ExportFieldType::Filter);
    expect($definition->valueType)->toBe(ExportFieldValueType::Multiselect);
    expect($definition->path)->toBe('filters.countries');
});

it('creates an option field definition with default path', function () {
    $definition = ExportFieldDefinition::option(
        key: 'include_rankings',
        label: 'Include Rankings',
    );

    expect($definition->key)->toBe('include_rankings');
    expect($definition->label)->toBe('Include Rankings');
    expect($definition->fieldType)->toBe(ExportFieldType::Option);
    expect($definition->valueType)->toBe(ExportFieldValueType::Boolean);
    expect($definition->path)->toBe('options.include_rankings');
});

it('allows custom path override', function () {
    $definition = ExportFieldDefinition::filter(
        key: 'countries',
        label: 'Countries',
        path: 'filters.filters.countries',
    );

    expect($definition->path)->toBe('filters.filters.countries');
});

it('resolves boolean values', function () {
    $definition = ExportFieldDefinition::option(
        key: 'include_rankings',
        label: 'Include Rankings',
        valueType: ExportFieldValueType::Boolean,
    );

    expect($definition->resolve(true))->toBe(['Yes']);
    expect($definition->resolve(false))->toBe(['No']);
});

it('resolves text values', function () {
    $definition = ExportFieldDefinition::filter(
        key: 'search',
        label: 'Search',
        valueType: ExportFieldValueType::Text,
    );

    expect($definition->resolve('hello'))->toBe(['hello']);
    expect($definition->resolve(['one', 'two']))->toBe(['one', 'two']);
});

it('resolves date values', function () {
    $definition = ExportFieldDefinition::filter(
        key: 'from',
        label: 'From Date',
        valueType: ExportFieldValueType::Date,
    );

    expect($definition->resolve('2024-01-01'))->toBe(['2024-01-01']);
});

it('uses custom resolver callback', function () {
    $definition = ExportFieldDefinition::filter(
        key: 'custom',
        label: 'Custom Field',
        valueType: ExportFieldValueType::Text,
        resolver: fn ($value) => array_map(fn ($v) => "Custom: $v", (array) $value),
    );

    expect($definition->resolve(['a', 'b']))->toBe(['Custom: a', 'Custom: b']);
});

it('resolves null values to empty array', function () {
    $definition = ExportFieldDefinition::filter(
        key: 'countries',
        label: 'Countries',
    );

    expect($definition->resolve(null))->toBe([]);
});

it('resolves values with cache', function () {
    $definition = ExportFieldDefinition::filter(
        key: 'countries',
        label: 'Countries',
        valueType: ExportFieldValueType::Multiselect,
        modelClass: Bernskiold\ExportEngine\Tests\Fixtures\User::class,
    );

    $cache = [
        1 => 'United States',
        2 => 'United Kingdom',
    ];

    expect($definition->resolveWithCache([1, 2], $cache))->toBe(['United States', 'United Kingdom']);
    expect($definition->resolveWithCache([1, 3], $cache))->toBe(['United States']); // Missing ID filtered out
});
