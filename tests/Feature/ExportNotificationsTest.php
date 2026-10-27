<?php

use Bernskiold\ExportEngine\Models\Export;
use Bernskiold\ExportEngine\Tests\Fixtures\TextExporter;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class ExportReadyForNotificationsTest extends Notification
{
    public function __construct(public Export $export) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}

class ExportFailedForNotificationsTest extends ExportReadyForNotificationsTest
{
    //
}

beforeEach(function () {
    NotificationFacade::fake();

    config(['export-engine.notifications' => [
        'finished' => ExportReadyForNotificationsTest::class,
        'failed' => ExportFailedForNotificationsTest::class,
    ]]);
});

it('tells the person when their queued export is ready', function () {
    $user = signIn();

    $export = Export::generate('Greeting.txt', 'txt', exporter: TextExporter::class)->dispatch();

    NotificationFacade::assertSentTo($user, ExportReadyForNotificationsTest::class, fn ($notification) => $notification->export->is($export));
});

it('tells the person when their queued export failed', function () {
    $user = signIn();

    Export::factory()->queued()->create(['user_id' => $user->id])->fail('Not allowed.');

    NotificationFacade::assertSentTo($user, ExportFailedForNotificationsTest::class);
});

it('sends nothing for an export that runs in the request', function () {
    signIn();

    Export::generate('Greeting.txt', 'txt', exporter: TextExporter::class)->dispatchSync();

    NotificationFacade::assertNothingSent();
});

it('sends nothing when the config has no notification', function () {
    config(['export-engine.notifications.finished' => null]);
    signIn();

    Export::generate('Greeting.txt', 'txt', exporter: TextExporter::class)->dispatch();

    NotificationFacade::assertNothingSent();
});
