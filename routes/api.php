<?php

use App\Http\Controllers\Api\MailLogController;
use Illuminate\Support\Facades\Route;

// Protected API routes (requires authentication)
Route::middleware('auth')->group(function () {
    Route::get('/mail-logs', [MailLogController::class, 'index']);
    Route::get('/mail-logs/stats', [MailLogController::class, 'stats']);
    Route::get('/mail-logs/chart-data', [MailLogController::class, 'chartData']);
});

// Public API endpoints for demo/testing (no auth required)
Route::get('/public/mail-logs', [MailLogController::class, 'index']);
Route::get('/public/mail-logs/stats', [MailLogController::class, 'stats']);
Route::get('/public/mail-logs/chart-data', [MailLogController::class, 'chartData']);