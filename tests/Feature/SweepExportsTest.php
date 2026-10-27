<?php

use Bernskiold\ExportEngine\Enums\ExportStatus;
use Bernskiold\ExportEngine\Models\Export;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    signIn();
});

it('expires the exports that are past their expiry time and deletes their files', function () {
    $old = Export::factory()->available()->create();
    $old->forceFill(['expires_at' => now()->subMinute()])->save();

    $current = Export::factory()->available()->create();

    $this->artisan('exports:sweep')->assertSuccessful();

    expect($old->refresh()->status)->toBe(ExportStatus::Expired)
        ->and($old->file)->toBeNull()
        ->and($current->refresh()->status)->toBe(ExportStatus::Available);

    Storage::disk('exports')->assertExists($current->file);
});

/**
 * A worker that stops during an export leaves it pending. Nothing else
 * changes it, so without the sweep it shows as pending always.
 */
it('fails the exports that stayed pending too long', function () {
    config(['export-engine.abandoned_after_minutes' => 120]);

    $stopped = Export::factory()->generating()->create(['started_at' => now()->subHours(3)]);
    $neverStarted = Export::factory()->create(['created_at' => now()->subHours(3)]);
    $running = Export::factory()->generating()->create(['started_at' => now()->subHour()]);

    $this->artisan('exports:sweep')->assertSuccessful();

    expect($stopped->refresh()->status)->toBe(ExportStatus::Failed)
        ->and($stopped->failure_reason)->toBe('The export did not finish. Please try again.')
        ->and($neverStarted->refresh()->status)->toBe(ExportStatus::Failed)
        ->and($running->refresh()->status)->toBe(ExportStatus::Generating);
});

it('deletes the files of failed exports', function () {
    $export = Export::factory()->available()->create();
    $export->forceFill(['status' => ExportStatus::Failed])->save();
    $file = $export->file;

    $this->artisan('exports:sweep')->assertSuccessful();

    Storage::disk('exports')->assertMissing($file);
    expect($export->refresh()->file)->toBeNull();
});

it('deletes old files from the staging directory only', function () {
    $disk = Storage::disk('staging');

    $disk->put('export-engine/stopped/old.csv', 'old');
    $disk->put('export-engine/running/new.csv', 'new');
    $disk->put('other/old.csv', 'not ours');

    touch($disk->path('export-engine/stopped/old.csv'), now()->subDays(2)->getTimestamp());
    touch($disk->path('other/old.csv'), now()->subDays(2)->getTimestamp());

    $this->artisan('exports:sweep')->assertSuccessful();

    $disk->assertMissing('export-engine/stopped/old.csv');
    $disk->assertExists('export-engine/running/new.csv');
    $disk->assertExists('other/old.csv');
});

it('does not sweep the root of the export disk', function () {
    config(['export-engine.staging' => ['disk' => 'exports', 'directory' => '']]);

    $export = Export::factory()->available()->create();
    touch(Storage::disk('exports')->path($export->file), now()->subDays(2)->getTimestamp());

    $this->artisan('exports:sweep')->assertSuccessful();

    Storage::disk('exports')->assertExists($export->file);
});

it('prunes old records only when the config says so', function () {
    $old = Export::factory()->create(['created_at' => now()->subDays(800)]);

    $this->artisan('model:prune', ['--model' => Export::class])->assertSuccessful();
    expect($old->fresh())->not->toBeNull();

    config(['export-engine.prune_after_days' => 730]);

    $this->artisan('model:prune', ['--model' => Export::class])->assertSuccessful();
    expect($old->fresh())->toBeNull();
});
