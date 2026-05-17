<?php

namespace App\Services\Notifications;

use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Services\Notifications\Channels\EmailChannel;
use App\Services\Notifications\Channels\TelegramChannel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class NotificationService
{
    public function createAndDispatch(array $payload): Notification
    {
        $notification = Notification::query()->create([
            'user_id' => $payload['user_id'],
            'channel' => $payload['channel'],
            'message' => $payload['message'],
            'status' => Notification::STATUS_PROCESSING,
        ]);

        SendNotificationJob::dispatch($notification->id);

        return $notification;
    }

    public function getStatus(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'status' => $notification->status,
            'attempts' => $notification->attempts,
            'error_message' => $notification->error_message,
            'sent_at' => $notification->sent_at,
        ];
    }

    public function getHistory(int $userId, array $filters = []): LengthAwarePaginator
    {
        $query = Notification::query()->forHistory($userId, $filters);

        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 15;

        return $query->latest('id')->paginate($perPage);
    }

    public function resolveChannel(string $channel): NotificationChannelInterface
    {
        if ($channel === Notification::CHANNEL_EMAIL) {
            return new EmailChannel();
        }

        if ($channel === Notification::CHANNEL_TELEGRAM) {
            return new TelegramChannel();
        }

        throw new ModelNotFoundException(sprintf('Unsupported channel: %s', $channel));
    }
}
