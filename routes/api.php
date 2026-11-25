<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\BroadcastJobController;
use App\Http\Controllers\Api\V1\BroadcastMediaController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/broadcast')->middleware(['hmac.m2m', 'throttle:60,1'])->group(function (): void {
    Route::post('/jobs', [BroadcastJobController::class, 'store'])->name('api.v1.broadcast.jobs.store');
    Route::post('/jobs/{jobId}/render', [BroadcastJobController::class, 'render'])->name('api.v1.broadcast.jobs.render');
    Route::get('/jobs/{jobId}', [BroadcastJobController::class, 'show'])->name('api.v1.broadcast.jobs.show');
    Route::post('/media/scan', [BroadcastMediaController::class, 'scan'])->name('api.v1.broadcast.media.scan');
    Route::post('/media/normalize', [BroadcastMediaController::class, 'normalize'])->name('api.v1.broadcast.media.normalize');
    Route::get('/media/normalize/{jobId}', [BroadcastMediaController::class, 'showNormalization'])->name('api.v1.broadcast.media.normalize.show');
    Route::get('/media/stream', [BroadcastMediaController::class, 'stream'])->name('api.v1.broadcast.media.stream');
});
