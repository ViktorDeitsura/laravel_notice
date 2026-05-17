<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_notification_and_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/notifications', [
            'user_id' => 10,
            'channel' => Notification::CHANNEL_EMAIL,
            'message' => 'Hello from feature test',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('status', Notification::STATUS_PROCESSING);

        $notificationId = (int) $response->json('id');

        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'user_id' => 10,
            'channel' => Notification::CHANNEL_EMAIL,
            'status' => Notification::STATUS_PROCESSING,
        ]);

        Queue::assertPushed(SendNotificationJob::class);
    }

    public function test_it_returns_notification_status(): void
    {
        $notification = Notification::query()->create([
            'user_id' => 5,
            'channel' => Notification::CHANNEL_TELEGRAM,
            'message' => 'Status check',
            'status' => Notification::STATUS_SENT,
            'attempts' => 2,
            'sent_at' => now(),
        ]);

        $this->getJson("/api/notifications/{$notification->id}/status")
            ->assertOk()
            ->assertJsonPath('id', $notification->id)
            ->assertJsonPath('status', Notification::STATUS_SENT)
            ->assertJsonPath('attempts', 2);
    }

    public function test_it_returns_filtered_notification_history(): void
    {
        Notification::query()->create([
            'user_id' => 15,
            'channel' => Notification::CHANNEL_EMAIL,
            'message' => 'Sent email',
            'status' => Notification::STATUS_SENT,
        ]);

        Notification::query()->create([
            'user_id' => 15,
            'channel' => Notification::CHANNEL_EMAIL,
            'message' => 'Failed email',
            'status' => Notification::STATUS_ERROR,
        ]);

        Notification::query()->create([
            'user_id' => 15,
            'channel' => Notification::CHANNEL_TELEGRAM,
            'message' => 'Failed telegram',
            'status' => Notification::STATUS_ERROR,
        ]);

        $response = $this->getJson('/api/users/15/notifications?status=error&channel=email&per_page=10');

        $response
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.status', Notification::STATUS_ERROR)
            ->assertJsonPath('data.0.channel', Notification::CHANNEL_EMAIL);
    }
}
