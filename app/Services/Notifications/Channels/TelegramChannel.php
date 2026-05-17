<?php

namespace App\Services\Notifications\Channels;

use App\Models\Notification;
use App\Services\Notifications\NotificationChannelInterface;

class TelegramChannel implements NotificationChannelInterface
{
    public function send(Notification $notification): void
    {
        if (str_contains($notification->message, '[force_error]')) {
            throw new \RuntimeException('Telegram channel delivery failed.');
        }
    }
}
