<?php

namespace Tests\Unit;

use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Services\Notifications\Channels\EmailChannel;
use App\Services\Notifications\Channels\TelegramChannel;
use App\Services\Notifications\NotificationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_dispatch_sets_processing_status(): void
    {
        Queue::fake();
        $service = new NotificationService();

        $notification = $service->createAndDispatch([
            'user_id' => 33,
            'channel' => Notification::CHANNEL_EMAIL,
            'message' => 'Unit message',
        ]);

        $this->assertSame(Notification::STATUS_PROCESSING, $notification->status);
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'status' => Notification::STATUS_PROCESSING,
        ]);
        Queue::assertPushed(SendNotificationJob::class);
    }

    public function test_resolve_channel_returns_email_implementation(): void
    {
        $service = new NotificationService();

        $channel = $service->resolveChannel(Notification::CHANNEL_EMAIL);

        $this->assertInstanceOf(EmailChannel::class, $channel);
    }

    public function test_resolve_channel_returns_telegram_implementation(): void
    {
        $service = new NotificationService();

        $channel = $service->resolveChannel(Notification::CHANNEL_TELEGRAM);

        $this->assertInstanceOf(TelegramChannel::class, $channel);
    }

    public function test_resolve_channel_throws_for_unsupported_channel(): void
    {
        $this->expectException(ModelNotFoundException::class);

        (new NotificationService())->resolveChannel('sms');
    }
}
