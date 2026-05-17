<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    public $tries = 5;

    /**
     * @var list<int>
     */
    public $backoff = [5, 15, 30, 60];

    /**
     * @var int
     */
    private $notificationId;

    public function __construct(int $notificationId)
    {
        $this->notificationId = $notificationId;
    }

    public function handle(NotificationService $notificationService): void
    {
        $notification = Notification::query()->find($this->notificationId);

        if (! $notification) {
            return;
        }

        $notification->forceFill([
            'attempts' => $this->attempts(),
            'status' => Notification::STATUS_PROCESSING,
            'error_message' => null,
        ])->save();

        try {
            $channel = $notificationService->resolveChannel($notification->channel);
            $channel->send($notification);
        } catch (Throwable $exception) {
            if ($this->attempts() < $this->tries) {
                $this->release($this->resolveBackoffDelay());

                return;
            }

            throw $exception;
        }

        $notification->forceFill([
            'status' => Notification::STATUS_SENT,
            'error_message' => null,
            'sent_at' => now(),
            'attempts' => $this->attempts(),
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        $notification = Notification::query()->find($this->notificationId);

        if (! $notification) {
            return;
        }

        $notification->forceFill([
            'status' => Notification::STATUS_ERROR,
            'error_message' => $exception ? $exception->getMessage() : null,
            'attempts' => $this->attempts(),
        ])->save();
    }

    private function resolveBackoffDelay(): int
    {
        $attemptIndex = max(0, $this->attempts() - 1);

        return $this->backoff[$attemptIndex] ?? end($this->backoff);
    }
}
