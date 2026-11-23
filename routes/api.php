<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\BroadcastJobController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/broadcast')->middleware(['hmac.m2m', 'throttle:60,1'])->group(function (): void {
    Route::post('/jobs', [BroadcastJobController::class, 'store'])->name('api.v1.broadcast.jobs.store');
    Route::get('/jobs/{jobId}', [BroadcastJobController::class, 'show'])->name('api.v1.broadcast.jobs.show');
});
