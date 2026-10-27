<?php

use Bernskiold\ExportEngine\ExportEngine;
use Bernskiold\ExportEngine\Models\Export;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('web')->group(fn () => ExportEngine::routes());
});

it('sends the file to the person who asked for it', function () {
    $user = signIn();
    $export = Export::factory()->available('Aviato,1')->create(['user_id' => $user->id, 'download_name' => 'Brands.csv']);

    $this->get(route('export-engine.download', $export->uuid))
        ->assertSuccessful()
        ->assertDownload('Brands.csv');

    expect($export->refresh()->downloaded_at)->not->toBeNull();
});

it('does not send the file to a different person', function () {
    $export = Export::factory()->available()->create(['user_id' => makeUser('Grace')->id]);

    signIn();

    $this->get(route('export-engine.download', $export->uuid))->assertForbidden();
});

it('does not send an expired file', function () {
    $user = signIn();
    $export = Export::factory()->available()->create(['user_id' => $user->id]);
    $export->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->get(route('export-engine.download', $export->uuid))->assertNotFound();
});

it('does not know an export that does not exist', function () {
    signIn();

    $this->get(route('export-engine.download', 'missing'))->assertNotFound();
});
