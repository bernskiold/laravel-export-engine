<?php

use Bernskiold\ExportEngine\Support\SheetNames;

it('keeps a short, clean name as it is', function () {
    expect((new SheetNames)->for('United Kingdom 2024'))->toBe('United Kingdom 2024');
});

it('cuts a long name down to 31 characters', function () {
    expect((new SheetNames)->for(str_repeat('a', 40)))->toHaveLength(31);
});

it('replaces the characters Excel does not allow in a sheet name', function () {
    expect((new SheetNames)->for('UK: 2024/25 [Adults]?*\\'))->toBe('UK  2024 25  Adults');
});

it('keeps names unique once they have been cut down', function () {
    $sheetNames = new SheetNames;

    $first = $sheetNames->for('ABC United Kingdom 2024 All Adults Wave 1');
    $second = $sheetNames->for('ABC United Kingdom 2024 All Adults Wave 2');
    $third = $sheetNames->for('abc united kingdom 2024 all adults wave 3');

    expect($first)->toBe('ABC United Kingdom 2024 All Adu')
        ->and($second)->toBe('ABC United Kingdom 2024 All (2)')
        ->and($third)->toBe('abc united kingdom 2024 all (3)')
        ->and([$first, $second, $third])->each->toHaveLength(31);
});

it('names an otherwise empty sheet', function () {
    expect((new SheetNames)->for('[]'))->toBe('Sheet');
});
