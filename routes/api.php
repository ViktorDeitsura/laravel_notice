<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('notifications')->group(function (): void {
    Route::post('/', [NotificationController::class, 'store']);
    Route::get('/{notification}/status', [NotificationController::class, 'status']);
});

Route::get('/users/{userId}/notifications', [NotificationController::class, 'history']);

Route::prefix('reports')->group(function (): void {
    Route::post('/', [ReportController::class, 'store']);
    Route::get('/{reportRequest}/status', [ReportController::class, 'status']);
    Route::get('/{reportRequest}/download', [ReportController::class, 'download']);
});