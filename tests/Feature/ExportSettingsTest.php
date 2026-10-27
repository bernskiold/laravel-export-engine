<?php

use Bernskiold\ExportEngine\Contracts\HasExportSettings;
use Bernskiold\ExportEngine\Models\Export;
use Bernskiold\ExportEngine\Settings\ChoiceSetting;
use Bernskiold\ExportEngine\Settings\ExportSettings;
use Bernskiold\ExportEngine\Settings\ExportSettingsContext;
use Bernskiold\ExportEngine\Settings\MultiChoiceSetting;
use Bernskiold\ExportEngine\Settings\NumberSetting;
use Bernskiold\ExportEngine\Settings\ToggleSetting;
use Bernskiold\ExportEngine\SpreadsheetExporter as Exporter;
use Bernskiold\ExportEngine\Tests\Fixtures\SheetLayout;
use Spatie\SimpleExcel\SimpleExcelWriter;

class ExporterWithSettingsForSettingsTest extends Exporter implements HasExportSettings
{
    public static function exportSettings(ExportSettingsContext $context): array
    {
        return [
            ChoiceSetting::make('sheets', 'Sheets')
                ->options(SheetLayout::class)
                ->default(SheetLayout::Combined)
                ->onlyForFormats('xlsx'),
            ToggleSetting::make('totals', 'Totals')
                ->default(true),
            ChoiceSetting::make('hidden', 'Hidden')
                ->options(['a' => 'A', 'b' => 'B'])
                ->default('a')
                ->visibleWhen(false),
            ChoiceSetting::make('page', 'Page')
                ->options(['left' => 'Left', 'right' => 'Right'])
                ->default(fn (ExportSettingsContext $context) => $context->option('side', 'left'))
                ->remembered(false),
        ];
    }

    protected function data(SimpleExcelWriter $writer): void
    {
        //
    }
}

function settingsFor(string $format = 'xlsx', array $options = []): ExportSettings
{
    return ExportSettings::for(ExporterWithSettingsForSettingsTest::class, new ExportSettingsContext(
        format: $format,
        options: $options,
        user: auth()->user(),
    ));
}

beforeEach(function () {
    signIn();
});

it('has nothing to offer for an exporter without settings', function () {
    expect(ExportSettings::for(Exporter::class, new ExportSettingsContext('xlsx'))->isEmpty())->toBeTrue()
        ->and(ExportSettings::for(null, new ExportSettingsContext('xlsx'))->isEmpty())->toBeTrue();
});

it('only offers the settings that are visible for the format', function () {
    expect(array_keys(settingsFor('xlsx')->offered()))->toBe(['sheets', 'totals', 'page'])
        ->and(array_keys(settingsFor('csv')->offered()))->toBe(['totals', 'page']);
});

it('opens with the defaults, resolved against the export', function () {
    expect(settingsFor(options: ['side' => 'right'])->initialValues())->toBe([
        'sheets' => 'combined',
        'totals' => true,
        'page' => 'right',
    ]);
});

it('opens with what was chosen last time where it is still valid', function () {
    $values = settingsFor()->initialValues([
        'sheets' => 'per_study',
        'totals' => false,
        'page' => 'right',
    ]);

    expect($values['sheets'])->toBe('per_study')
        ->and($values['totals'])->toBeFalse()
        // Not remembered, so the default wins over last time.
        ->and($values['page'])->toBe('left');
});

it('drops a remembered choice that is no longer one of the options', function () {
    expect(settingsFor()->initialValues(['sheets' => 'per_brand'])['sheets'])->toBe('combined');
});

it('validates the answers against the options', function () {
    $settings = settingsFor();

    $validator = Validator::make(
        ['settings' => ['sheets' => 'per_brand', 'totals' => 'yes', 'page' => 'left']],
        $settings->rules(),
    );

    expect($validator->errors()->keys())->toBe(['settings.sheets', 'settings.totals']);
});

it('stores only the answers to what was asked', function () {
    expect(settingsFor('csv')->chosen(['sheets' => 'per_study', 'totals' => false, 'hidden' => 'b', 'extra' => 1]))
        ->toBe(['totals' => false]);
});

it('hands the exporter the stored value, cast', function () {
    $export = Export::factory()->create([
        'exporter' => ExporterWithSettingsForSettingsTest::class,
        'format' => 'xlsx',
        'settings' => ['sheets' => 'per_study', 'totals' => false],
    ]);

    expect($export->setting('sheets'))->toBe(SheetLayout::PerStudy)
        ->and($export->setting('totals'))->toBeFalse();
});

it('falls back to the default for anything not asked', function () {
    $export = Export::factory()->create([
        'exporter' => ExporterWithSettingsForSettingsTest::class,
        'format' => 'csv',
        // Sheets are not offered for a CSV, so a stored value is ignored.
        'settings' => ['sheets' => 'per_study', 'hidden' => 'b'],
    ]);

    expect($export->setting('sheets'))->toBe(SheetLayout::Combined)
        ->and($export->setting('totals'))->toBeTrue()
        ->and($export->setting('hidden'))->toBe('a')
        ->and($export->setting('undeclared'))->toBeNull();
});

it('is null for an exporter that has no settings', function () {
    $export = Export::factory()->create(['exporter' => null, 'settings' => ['sheets' => 'per_study']]);

    expect($export->setting('sheets'))->toBeNull();
});

it('remembers the last settings the user chose for the exporter', function () {
    $user = auth()->user();

    Export::factory()->create(['user_id' => $user->id] + [
        'exporter' => ExporterWithSettingsForSettingsTest::class,
        'settings' => ['sheets' => 'combined'],
    ]);

    Export::factory()->create(['user_id' => $user->id] + [
        'exporter' => ExporterWithSettingsForSettingsTest::class,
        'settings' => ['sheets' => 'per_study'],
    ]);

    // Someone else's, and another exporter's, are not this user's last choice.
    Export::factory()->create([
        'user_id' => makeUser('Grace')->id,
        'exporter' => ExporterWithSettingsForSettingsTest::class,
        'settings' => ['sheets' => 'combined'],
    ]);

    Export::factory()->create(['user_id' => $user->id] + [
        'exporter' => Exporter::class,
        'settings' => ['sheets' => 'combined'],
    ]);

    expect(ExportSettings::lastChosenBy($user, ExporterWithSettingsForSettingsTest::class))
        ->toBe(['sheets' => 'per_study'])
        ->and(ExportSettings::lastChosenBy(null, ExporterWithSettingsForSettingsTest::class))->toBe([]);
});

class ExporterWithDependentSettingsForSettingsTest extends Exporter implements HasExportSettings
{
    public static function exportSettings(ExportSettingsContext $context): array
    {
        return [
            ChoiceSetting::make('size', 'Size')
                ->options(['small' => 'Small', 'custom' => 'Custom'])
                ->default('small'),
            NumberSetting::make('width', 'Width')
                ->between(100, 500)
                ->default(200)
                ->showWhen('size', 'custom'),
            MultiChoiceSetting::make('groups', 'Groups')
                ->options(SheetLayout::class),
        ];
    }

    protected function data(SimpleExcelWriter $writer): void
    {
        //
    }
}

function dependentSettings(): ExportSettings
{
    return ExportSettings::for(ExporterWithDependentSettingsForSettingsTest::class, new ExportSettingsContext('xlsx'));
}

it('only validates a setting that depends on another when it is shown', function () {
    $rules = dependentSettings()->rules();

    expect(Validator::make(['settings' => ['size' => 'small', 'width' => 'wide', 'groups' => ['combined']]], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['settings' => ['size' => 'custom', 'width' => 50, 'groups' => ['combined']]], $rules)->errors()->keys())
        ->toBe(['settings.width']);
});

it('only stores a dependent setting when it is shown', function () {
    expect(dependentSettings()->chosen(['size' => 'small', 'width' => 300, 'groups' => ['combined']]))
        ->toBe(['size' => 'small', 'groups' => ['combined']])
        ->and(dependentSettings()->chosen(['size' => 'custom', 'width' => 300, 'groups' => ['combined']]))
        ->toBe(['size' => 'custom', 'width' => 300, 'groups' => ['combined']]);
});

it('reads a dependent setting only when it applies', function () {
    expect(dependentSettings()->value('width', ['size' => 'custom', 'width' => '300']))->toBe(300)
        ->and(dependentSettings()->value('width', ['size' => 'small', 'width' => '300']))->toBe(200);
});

it('picks every option of a multiple choice by default', function () {
    expect(dependentSettings()->initialValues()['groups'])->toBe(['combined', 'per_study', 'per_audience'])
        ->and(dependentSettings()->value('groups'))->toBe([SheetLayout::Combined, SheetLayout::PerStudy, SheetLayout::PerAudience]);
});

it('needs at least one of a multiple choice, and only the options offered', function (array $groups, bool $passes) {
    $validator = Validator::make(
        ['settings' => ['size' => 'small', 'groups' => $groups]],
        dependentSettings()->rules(),
    );

    expect($validator->passes())->toBe($passes);
})->with([
    'one' => [['per_study'], true],
    'both' => [['combined', 'per_study'], true],
    'none' => [[], false],
    'unknown' => [['combined', 'per_brand'], false],
]);

it('groups settings for the form: rows side by side, runs of checkboxes together', function () {
    $settings = ExportSettings::for(ExporterWithRowsForSettingsTest::class, new ExportSettingsContext('xlsx'));

    $groups = collect($settings->fieldGroups(['size' => 'custom']))
        ->map(fn (array $group) => $group['layout'].':'.collect($group['settings'])->pluck('key')->implode(','))
        ->all();

    expect($groups)->toBe(['stack:size', 'row:width,height', 'stack:title,legend', 'stack:scale', 'stack:source'])
        ->and(collect($settings->fieldGroups(['size' => 'small']))->pluck('settings.0.key')->all())
        ->toBe(['size', 'title', 'scale', 'source']);
});

class ExporterWithRowsForSettingsTest extends Exporter implements HasExportSettings
{
    public static function exportSettings(ExportSettingsContext $context): array
    {
        return [
            ChoiceSetting::make('size', 'Size')->options(['small' => 'Small', 'custom' => 'Custom'])->default('small'),
            NumberSetting::make('width', 'Width')->default(100)->showWhen('size', 'custom')->inRow('dimensions'),
            NumberSetting::make('height', 'Height')->default(100)->showWhen('size', 'custom')->inRow('dimensions'),
            ToggleSetting::make('title', 'Title'),
            ToggleSetting::make('legend', 'Legend'),
            ChoiceSetting::make('scale', 'Scale')->options(['1' => '1'])->default('1'),
            ToggleSetting::make('source', 'Source'),
        ];
    }

    protected function data(SimpleExcelWriter $writer): void
    {
        //
    }
}
