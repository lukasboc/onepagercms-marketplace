<?php

use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\UpdateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:60,1')->group(function () {
    Route::get('/items', [ItemController::class, 'index']);
    Route::get('/items/{slug}', [ItemController::class, 'show']);
    Route::get('/items/{slug}/download', [ItemController::class, 'download']);
    Route::get('/updates', [UpdateController::class, 'check']);
});
