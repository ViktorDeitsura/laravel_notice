<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationHistoryRequest;
use App\Http\Requests\StoreNotificationRequest;
use App\Models\Notification;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /**
     * @var NotificationService
     */
    private $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function store(StoreNotificationRequest $request): JsonResponse
    {
        $notification = $this->notificationService->createAndDispatch($request->validated());

        return response()->json([
            'id' => $notification->id,
            'status' => $notification->status,
        ], 201);
    }

    public function status(Notification $notification): JsonResponse
    {
        return response()->json($this->notificationService->getStatus($notification));
    }

    public function history(NotificationHistoryRequest $request, int $userId): JsonResponse
    {
        $history = $this->notificationService->getHistory($userId, $request->validated());

        return response()->json($history);
    }
}
