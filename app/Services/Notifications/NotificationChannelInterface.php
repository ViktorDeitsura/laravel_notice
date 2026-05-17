<?php

namespace App\Services\Notifications;

use App\Models\Notification;

interface NotificationChannelInterface
{
    public function send(Notification $notification): void;
}
