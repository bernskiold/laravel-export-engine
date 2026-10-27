<?php

use Bernskiold\ExportEngine\Data\PendingExport;
use Bernskiold\ExportEngine\Enums\ExportStatus;
use Bernskiold\ExportEngine\Models\Export;
use Bernskiold\ExportEngine\Settings\ExportSettings;
use Bernskiold\ExportEngine\Tests\Fixtures\BrandsExporter;
use Bernskiold\ExportEngine\Tests\Fixtures\ExporterWithSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Spatie\SimpleExcel\SimpleExcelReader;

function pendingExport(string $exporter = ExporterWithSettings::class, ?int $userId = null): PendingExport
{
    return new PendingExport(
        exporter: $exporter,
        downloadName: 'brand-overview',
        format: 'csv',
        filters: ['brands' => ['Aviato']],
        userId: $userId ?? auth()->id(),
        filenameLabels: ['Aviato', 'UK 2025'],
    );
}

beforeEach(function () {
    signIn();
});

it('makes the record and starts the export', function () {
    Carbon::setTestNow('2026-10-25 09:30:00');

    $export = pendingExport(BrandsExporter::class)->start();

    expect($export->status)->toBe(ExportStatus::Available)
        ->and($export->download_name)->toBe('Brand Overview - Aviato - UK 2025 - 2026-10-25 09-30.csv')
        ->and($export->user_id)->toBe(auth()->id());
});

it('keeps more columns for your own model', function () {
    $pending = new PendingExport(exporter: BrandsExporter::class, downloadName: 'Brands', format: 'csv', attributes: ['name' => 'My brands']);

    expect($pending->start()->name)->toBe('My brands');
});

it('holds the export for the same person only', function () {
    $token = pendingExport()->hold();

    expect(PendingExport::find($token, auth()->user()))->toBeInstanceOf(PendingExport::class)
        ->and(PendingExport::find($token, makeUser('Grace')))->toBeNull()
        ->and(PendingExport::find($token, null))->toBeNull();

    PendingExport::release($token);

    expect(PendingExport::find($token, auth()->user()))->toBeNull();
});

it('has nothing to ask for an exporter with no settings', function () {
    expect(pendingExport(BrandsExporter::class)->settings()->isEmpty())->toBeTrue()
        ->and(pendingExport()->settings()->isEmpty())->toBeFalse();
});

it('gives the kind of field for each setting', function () {
    $types = collect(pendingExport()->settings()->offered())->map->type()->all();

    expect($types)->toBe([
        'sheets' => 'choice',
        'order' => 'dropdown',
        'size' => 'segmented',
        'columns' => 'multi-choice',
        'limit' => 'number',
        'totals' => 'toggle',
    ]);
});

it('starts a held export with the settings the person selected', function () {
    $token = pendingExport()->hold();

    $pending = PendingExport::find($token, auth()->user());
    $settings = $pending->settings();

    $values = [...$settings->initialValues(), 'sheets' => 'per_study', 'limit' => 25, 'totals' => true];

    expect(Validator::make(['settings' => $values], $settings->rules())->passes())->toBeTrue();

    PendingExport::release($token);

    $export = $pending->start($settings->chosen($values));

    expect($export->settings)->toMatchArray(['sheets' => 'per_study', 'limit' => 25, 'totals' => true])
        ->and(SimpleExcelReader::create($export->fullFilePath())->getRows()->first())->toBe(['sheets' => 'per_study', 'limit' => '25', 'totals' => 'yes']);
});

it('starts the values from the last export of the person', function () {
    Export::generate('Earlier.csv', 'csv', exporter: ExporterWithSettings::class, settings: ['sheets' => 'per_audience', 'limit' => 50]);

    $values = pendingExport()->settings()->initialValues(ExportSettings::lastChosenBy(auth()->user(), ExporterWithSettings::class));

    expect($values['sheets'])->toBe('per_audience')
        ->and($values['limit'])->toBe(50);
});

it('does not accept a setting that is not valid', function () {
    $settings = pendingExport()->settings();

    expect(Validator::make(['settings' => [...$settings->initialValues(), 'limit' => 500]], $settings->rules())->errors()->keys())
        ->toBe(['settings.limit']);
});
