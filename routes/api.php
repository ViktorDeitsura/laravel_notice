<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('notifications')->group(function (): void {
    Route::post('/', [NotificationController::class, 'store']);
    Route::get('/{notification}/status', [NotificationController::class, 'status']);
});

Route::get('/users/{userId}/notifications', [NotificationController::class, 'history']);