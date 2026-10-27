<?php

use Bernskiold\ExportEngine\Support\ExportFileName;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-10-21 14:03:22');

    config(['export-engine.file_names.acronyms' => ['AO', 'CX']]);
});

it('names an export by its title, its filters and when it was made', function () {
    expect(ExportFileName::make('Brand Pillars', ['Nike, Adidas +3', 'UK 2025', 'All Adults'])->toString('xlsx'))
        ->toBe('Brand Pillars - Nike, Adidas +3 - UK 2025 - All Adults - 2026-10-21 14-03.xlsx');
});

it('leaves the extension off for Highcharts to add', function () {
    expect(ExportFileName::make('Brand Pillars', ['UK 2025'])->toString())
        ->toBe('Brand Pillars - UK 2025 - 2026-10-21 14-03');
});

it('skips empty labels', function () {
    expect(ExportFileName::make('Brand Pillars', [null, '', '  ', 'UK 2025'])->toString('pptx'))
        ->toBe('Brand Pillars - UK 2025 - 2026-10-21 14-03.pptx');
});

it('turns a slug into words, with the acronyms of the config in capitals', function (string $slug, string $title) {
    expect(ExportFileName::make($slug)->toString('xlsx'))->toBe("{$title} - 2026-10-21 14-03.xlsx");
})->with([
    ['commitment-funnel', 'Commitment Funnel'],
    ['ao-children', 'AO Children'],
    ['cx-report', 'CX Report'],
    ['best-countries-powerranks', 'Best Countries Powerranks'],
    ['data', 'Data'],
]);

it('leaves a title already written in words alone', function () {
    expect(ExportFileName::make('Annual Management Report')->toString('pptx'))
        ->toBe('Annual Management Report - 2026-10-21 14-03.pptx');
});

it('strips characters a file system or the download header will not take', function () {
    expect(ExportFileName::make('Brand Pillars', ['AC/DC', 'Q1: "Best" <brands>?', "Tab\tand\nnewline"])->toString('xlsx'))
        ->toBe("Brand Pillars - AC-DC - Q1 - 'Best' brands - Tab and newline - 2026-10-21 14-03.xlsx");
});

it('keeps accented and non-Latin names', function () {
    expect(ExportFileName::make('Brand Pillars', ['Nestlé', '可口可乐'])->toString('xlsx'))
        ->toBe('Brand Pillars - Nestlé - 可口可乐 - 2026-10-21 14-03.xlsx');
});

it('drops the least significant filters once the name runs long', function () {
    $name = ExportFileName::make('Brand Pillars', [
        str_repeat('Brand ', 15),
        str_repeat('Study ', 15),
        'All Adults',
    ])->toString('xlsx');

    expect(mb_strlen($name))->toBeLessThanOrEqual(ExportFileName::MAX_LENGTH)
        ->and($name)->toStartWith('Brand Pillars - Brand Brand')
        ->and($name)->not->toContain('All Adults')
        ->and($name)->toEndWith(' - 2026-10-21 14-03.xlsx');
});

it('cuts a label short rather than leave a long gap', function () {
    $name = ExportFileName::make('Brand Pillars', [str_repeat('Coca-Cola ', 30)])->toString('xlsx');

    expect(mb_strlen($name))->toBe(ExportFileName::MAX_LENGTH)
        ->and($name)->toContain('…');
});

it('keeps the title and date however long the title is', function () {
    $name = ExportFileName::make(str_repeat('Very Long Tool Name ', 20), ['UK 2025'])->toString('xlsx');

    expect(mb_strlen($name))->toBeLessThanOrEqual(ExportFileName::MAX_LENGTH)
        ->and($name)->toStartWith('Very Long Tool Name')
        ->and($name)->toEndWith('… - 2026-10-21 14-03.xlsx');
});

it('stays within the byte budget for names in multi-byte scripts', function () {
    $name = ExportFileName::make('Brand Pillars', [str_repeat('可口可乐', 40)])->toString('xlsx');

    expect(strlen($name))->toBeLessThanOrEqual(ExportFileName::MAX_BYTES)
        ->and(mb_check_encoding($name, 'UTF-8'))->toBeTrue();
});

it('lists the first names of a filter and counts the rest', function () {
    expect(ExportFileName::list(['Nike', 'Adidas', 'Puma', 'Reebok']))->toBe('Nike, Adidas +2')
        ->and(ExportFileName::list(['Nike', 'Adidas']))->toBe('Nike, Adidas')
        ->and(ExportFileName::list(['Nike', 'Nike', null, '']))->toBe('Nike')
        ->and(ExportFileName::list([2024, 2025, 2026], show: 3))->toBe('2024, 2025, 2026')
        ->and(ExportFileName::list([]))->toBe('');
});
