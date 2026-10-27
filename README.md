# Laravel Export Engine

[![Latest Version on Packagist](https://img.shields.io/packagist/v/bernskiold/laravel-export-engine.svg?style=flat-square)](https://packagist.org/packages/bernskiold/laravel-export-engine)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/bernskiold/laravel-export-engine/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/bernskiold/laravel-export-engine/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/bernskiold/laravel-export-engine/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/bernskiold/laravel-export-engine/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/bernskiold/laravel-export-engine.svg?style=flat-square)](https://packagist.org/packages/bernskiold/laravel-export-engine)

Make files from your data, on the queue or in the request: spreadsheets, decks and more. The engine keeps a record of each export. It can ask the person for settings before the export starts. It moves the file to a disk of your choice and deletes it when it expires.

The engine is headless. It has no views and no components. Your application shows the forms, the lists and the notifications.

## What is included

- **An export record** (`Models\Export`) with a clear life: requested, generating, available, failed, expired. A queued export stays requested until a worker starts it. The record keeps the failure reason for the person, notes from the exporter, the disk of the file, and when the person downloaded it.
- **Exporters.** Any class that implements `Contracts\Exporter` can make a file. `SpreadsheetExporter` writes CSV and Excel files with headers, frozen panes, column widths and more than one sheet. It works with OpenSpout 4 and OpenSpout 5.
- **Jobs.** Exports run on the queue or in the request, with one job for each export. Middleware wraps each export, for example to set the locale. An exporter can require an ability, and the engine checks it again before the export runs.
- **Settings.** An exporter can ask how the person wants their file. The engine holds the export in the cache while your form asks for the settings. It gives the rules to validate the values, and gives the values to the exporter.
- **Field labels.** An exporter can tell how to show its filters and options in a list of exports.
- **File names** that tell what the export is, its filters and when the engine made it.
- **A sweep command** that expires old files, fails abandoned exports and deletes staged files.
- **A download route, a policy and notifications** that you can use or replace.
- **Test helpers** for the tests of your application.

## Requirements

- PHP 8.4 or later
- Laravel 12 or 13
- OpenSpout 4.30 or later, or OpenSpout 5

## Installation

Install the package with Composer:

```bash
composer require bernskiold/laravel-export-engine
```

Publish the config and the migration, then run the migration:

```bash
php artisan vendor:publish --tag=export-engine-config
php artisan vendor:publish --tag=export-engine-migrations
php artisan migrate
```

Schedule the sweep in `routes/console.php`:

```php
Schedule::command('exports:sweep')->hourly();
```

To delete old records, set `prune_after_days` and schedule `model:prune`:

```php
Schedule::command('model:prune', ['--model' => [Export::class]])->daily();
```

### Storage

An exporter always writes its file to the local staging disk first, because the spreadsheet and presentation writers need a real path. When the file is complete, the engine moves it to the export disk. The export disk can be local or S3.

```php
'disk' => 'exports',

'staging' => [
    'disk' => 'local',
    'directory' => 'export-engine',
],
```

The sweep deletes files in the staging directory that are older than `staging_files_after_hours`. Use a directory, or a disk, that only the engine uses.

## Usage

### Write an exporter

A spreadsheet exporter writes its rows in `data()`. The export gives the filters, the options and the format.

```php
use Bernskiold\ExportEngine\Contracts\WithHeader;
use Bernskiold\ExportEngine\SpreadsheetExporter;
use Spatie\SimpleExcel\SimpleExcelWriter;

class BrandsExporter extends SpreadsheetExporter implements WithHeader
{
    public function header(): array
    {
        return ['Brand', 'Rank'];
    }

    protected function data(SimpleExcelWriter $writer): void
    {
        Brand::query()
            ->whereIn('id', $this->export->filter('brands'))
            ->each(fn (Brand $brand) => $writer->addRow([
                'Brand' => $brand->name,
                'Rank' => $brand->rank,
            ]));
    }
}
```

Add these contracts for more control:

| Contract | What it does |
| --- | --- |
| `WithHeader` | Writes a header row. A CSV file gets the headers as slugs. |
| `FreezesPanes` | Freezes rows and columns in an Excel file. |
| `SizesColumns` | Sets the column widths in an Excel file. |
| `ConfiguresWriter` | Changes the OpenSpout writer before it writes the first row. |
| `HasExportSettings` | Asks the person for settings before the export starts. |
| `DefinesExportFields` | Tells how to show the filters and options of an export. |
| `DescribesExport` | Gives the name of the export for a person. |
| `RequiresAbility` | Lets only a person with a Gate ability use the exporter. |
| `AuthorizesExport` | Checks the person again when the export runs, with your own rule. |

For a file that is not a spreadsheet, implement `Contracts\Exporter`. The engine gives a local path without an extension. Write the file and return where it is. Notes go to the person who asked.

```php
use Bernskiold\ExportEngine\Contracts\Exporter;
use Bernskiold\ExportEngine\Data\ExportedFile;
use Bernskiold\ExportEngine\Models\Export;

class SalesReportExporter implements Exporter
{
    public function __construct(protected BuildSalesReport $build) {}

    public function write(Export $export, string $path): ExportedFile
    {
        $report = $this->build->execute($export->filter('quarter'), $export->requester());

        $report->presentation->save("{$path}.pptx");

        return ExportedFile::at("{$path}.pptx", notes: $report->skippedRegions);
    }
}
```

The engine makes the exporter from the container, with the export as the `$export` argument.

### Start an export

Use a `PendingExport`:

```php
use Bernskiold\ExportEngine\Data\PendingExport;

$export = (new PendingExport(
    exporter: BrandsExporter::class,
    downloadName: 'Brand Overview',
    format: 'xlsx',
    filters: ['brands' => [1, 2, 3]],
    queued: true,
    userId: auth()->id(),
    filenameLabels: ['Nike, Adidas +1', 'UK 2025'],
))->start();
```

The download name of this export is "Brand Overview - Nike, Adidas +1 - UK 2025 - 2026-10-25 09-30.xlsx".

An export that runs in the request is available when `start()` returns. Send the file with `$export->download()`. A queued export sends the notification of the config when it is ready or when it fails.

You can also make the record yourself with `Export::generate()`, then call `dispatch()` or `dispatchSync()`.

### Ask for settings

Declare the settings on the exporter:

```php
use Bernskiold\ExportEngine\Contracts\HasExportSettings;
use Bernskiold\ExportEngine\Settings\ChoiceSetting;
use Bernskiold\ExportEngine\Settings\ExportSettingsContext;
use Bernskiold\ExportEngine\Settings\ToggleSetting;

class BrandsExporter extends SpreadsheetExporter implements HasExportSettings
{
    public static function exportSettings(ExportSettingsContext $context): array
    {
        return [
            ChoiceSetting::make('sheets', __('Sheets'))
                ->options(SheetLayout::class)
                ->default(SheetLayout::Combined)
                ->onlyForFormats('xlsx'),
            ToggleSetting::make('totals', __('Totals'))->default(true),
        ];
    }

    protected function data(SimpleExcelWriter $writer): void
    {
        if ($this->setting('totals')) {
            // ...
        }
    }
}
```

The setting types are `ChoiceSetting`, `MultiChoiceSetting`, `NumberSetting` and `ToggleSetting`. Extend `ExportSetting` for a type of your own.

Each setting has a `type()`: `choice`, `dropdown`, `segmented`, `multi-choice`, `number` or `toggle`. Use it to select the field your form shows.

Before the export starts, hold it and open your form with the token:

```php
$pending = new PendingExport(/* ... */);

if ($pending->settings()->isEmpty()) {
    return $pending->start()->download();
}

$token = $pending->hold();
```

Your form gets the held export, the values to start with and the rules:

```php
$pending = PendingExport::find($token, auth()->user());
$settings = $pending->settings();

$values = $settings->initialValues(ExportSettings::lastChosenBy(auth()->user(), $pending->exporter));
$rules = $settings->rules();                     // Keys are "settings.{key}".
$groups = $settings->fieldGroups($values);       // The settings to show, in rows and stacks.
```

When the person submits the form, validate the values and start the export:

```php
PendingExport::release($token);

$export = $pending->start($settings->chosen($values));
```

### Middleware

Middleware wraps each export while it runs. Use it to set the locale, or to record who the work is for. A queued export has no signed-in user.

```php
class RunAsRequester
{
    public function handle(Export $export, Closure $next): mixed
    {
        return AccessContext::onBehalfOf($export->requester(), fn () => $next($export));
    }
}
```

Add middleware in the config, or in a service provider:

```php
ExportEngine::middleware([RunAsRequester::class]);
```

To stop an export from middleware or from an exporter, throw `ExportNotAllowed::because('...')`. The export fails with this message, and the job does not try again.

### Show the filters of an export

An exporter with `DefinesExportFields` tells how to show its filters and options. Each field gets its labels from a model, a closure or the value:

```php
public static function fieldDefinitions(): ExportFields
{
    return ExportFields::make([
        ExportFieldDefinition::filter('brands', __('Brands'), modelClass: Brand::class),
        ExportFieldDefinition::option('include_totals', __('Totals')),
    ]);
}
```

`$export->filterLabels()` gives a list of `ResolvedExportField` objects, with `label`, `values`, `firstValue()` and `otherValuesCount()`. For a list of exports, use `ExportFields::resolveForExports($exports)`. It finds the models of each model class in one query for all the exports.

### Labels and abilities

An exporter can give its name, and require a Gate ability:

```php
class SalesReportExporter implements DescribesExport, Exporter, RequiresAbility
{
    public static function exportLabel(): string
    {
        return __('Sales report');
    }

    public static function exportAbility(): string
    {
        return 'export sales reports';
    }
}
```

`$export->label()` gives the name of the export, or else the label of its exporter. `ExportEngine::canExport($user, SalesReportExporter::class)` tells if a person can use the exporter, for example to show a button. A pending export does not start for a person without the ability. A queued export fails when the person lost the ability before it ran.

### Run an export again

`$export->dispatch()` starts the export again on the same record. The old file expires first. `$export->runAgain()` makes a new record from the export and starts it, so the old record stays in the history.

### Permanent exporter names

By default, an export keeps the class name of its exporter. To move or rename exporters later, give them permanent names, the same as a morph map:

```php
ExportEngine::exporters([
    'sales-report' => SalesReportExporter::class,
]);
```

### Jobs of your own

Most exports use the default job, `Jobs\RunExport`. For an export that needs a different job, extend `Jobs\ExportJob` and set `jobClass` on the pending export. The job gets the middleware and the failure handling of the engine.

```php
class PowerpointDeckExport extends ExportJob
{
    public function process(): void
    {
        $path = $this->export->exporterClass()::make()->create()->save($this->export->uuid);

        $this->export->storeFileFromLocal($path, "decks/{$this->export->uuid}.pptx");
    }
}
```

### Downloads, policy and notifications

Register the download route in a route group:

```php
Route::middleware(['web', 'auth'])->group(fn () => ExportEngine::routes());
```

The route checks the `download` ability of the policy. The default policy lets only the owner see and download an export. Set `policy` in the config to use your own policy.

Set the notifications in the config. Each notification gets the export in its constructor.

```php
'notifications' => [
    'finished' => App\Notifications\ExportIsReady::class,
    'failed' => App\Notifications\ExportFailed::class,
],
```

### Your own model

To add columns, relations or scopes, extend the model and set it in the config:

```php
class Export extends \Bernskiold\ExportEngine\Models\Export
{
    use LogsActivity;
}
```

```php
'model' => App\Models\Export::class,
```

To add columns when an export starts, give them as `attributes` to the pending export.

### Test helpers

Use the `Testing\InteractsWithExports` trait in your tests:

```php
uses(InteractsWithExports::class);

it('exports the brands on the page', function () {
    livewire(BrandsPage::class)->call('export');

    $export = $this->assertExportStarted(BrandsExporter::class);

    expect($this->rowsOfExport($this->runExportAsWorker($export)))->toHaveCount(3);
});
```

`runExportAsWorker()` runs the export again with no signed-in user, the same as a queue worker. This shows a query that uses `auth()->user()` and not the person who asked for the export.

## Configuration

Read `config/export-engine.php` for all the options. The most important are:

| Key | Default | What it does |
| --- | --- | --- |
| `disk` | `local` | The disk for complete files. |
| `staging.disk` | `local` | The local disk where exporters write. |
| `expire_after_hours` | `168` | How long a file stays available. |
| `abandoned_after_minutes` | `1440` | How long an export can stay pending before the sweep fails it. |
| `prune_after_days` | `null` | How long the records stay. Null keeps them always. |
| `queue.queue` | `null` | The queue of queued exports. |

## Testing

```bash
composer test
composer phpstan
composer format
```

The tests use OpenSpout 5. To run them with OpenSpout 4:

```bash
composer update openspout/openspout --with openspout/openspout:^4.30 -W
```

## Changelog

Read the [CHANGELOG](CHANGELOG.md) for the changes in each release.

## Contributing

Read [CONTRIBUTING](CONTRIBUTING.md) for the details.

## Security Vulnerabilities

Read [our security policy](SECURITY.md) to report a security problem.

## Credits

- [Erik Bernskiöld](https://bernskiold.com)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Read the [License File](LICENSE.md) for more information.
