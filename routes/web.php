<?php

use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\Developer\ItemController;
use App\Http\Controllers\Developer\ItemVersionController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Public catalog — no login required.
Route::get('/', [CatalogController::class, 'landing'])->name('landing');
Route::get('/plugins', [CatalogController::class, 'plugins'])->name('catalog.plugins');
Route::get('/themes', [CatalogController::class, 'themes'])->name('catalog.themes');
Route::get('/items/{slug}', [CatalogController::class, 'show'])->name('catalog.show');
Route::get('/developers', [CatalogController::class, 'developers'])->name('developers');

Route::get('/dashboard', function () {
    return auth()->user()->isAdmin()
        ? redirect()->route('admin.review.index')
        : redirect()->route('developer.items.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->prefix('developer')->name('developer.')->group(function () {
    Route::get('/items', [ItemController::class, 'index'])->name('items.index');
    Route::get('/items/create', [ItemController::class, 'create'])->name('items.create');
    Route::post('/items', [ItemController::class, 'store'])->name('items.store');
    Route::get('/items/{item}', [ItemController::class, 'show'])->name('items.show');
    Route::post('/items/{item}/versions', [ItemVersionController::class, 'store'])->name('items.versions.store');
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/review', [ReviewController::class, 'index'])->name('review.index');
    Route::get('/review/{version}', [ReviewController::class, 'show'])->name('review.show');
    Route::post('/review/{version}/approve', [ReviewController::class, 'approve'])->name('review.approve');
    Route::post('/review/{version}/reject', [ReviewController::class, 'reject'])->name('review.reject');
    Route::get('/review/{version}/download', [ReviewController::class, 'download'])->name('review.download');
});

require __DIR__.'/auth.php';
