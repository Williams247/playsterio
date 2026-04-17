<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MusicCategoryController;
use App\Http\Controllers\MusicController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StorageSigningController;

# Health check endpoint
Route::get('/health', [HealthController::class, 'ping']);
Route::get('/health/storage-signing', [StorageSigningController::class, 'storageSigningHealth'])
    ->middleware('throttle:api');

# Auth route
Route::middleware('throttle:auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register_user']);
    Route::post('/login', [AuthController::class, 'login_user']);
    Route::post('/forgot-password', [AuthController::class, 'forgot_password']);
    Route::patch('/reset-password', [AuthController::class, 'reset_password']);
});

# Music route
Route::middleware('throttle:api')->group(function () {
    Route::post('/save-music', [MusicController::class, 'save_music']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/sign-download', [StorageSigningController::class, 'signDownload'])
            ->middleware('throttle:download-sign');

        # Music routes
        Route::get('/fetch-music-categories', [MusicCategoryController::class, 'get_music_categories']);
        Route::get('/fetch-music', [MusicController::class, 'get_music']);
        Route::post('/init-save-music', [MusicController::class, 'init_create_music']);
        Route::post('/create-music-category', [MusicCategoryController::class, 'create_music_category']);
        Route::patch('/update-music-category', [MusicController::class, 'edit_music_category']);
        Route::post('/enable-disable-music', [MusicController::class, 'enable_disable_music']);

        # Logout user
        Route::delete('/logout', [AuthController::class, 'logout_user']);
    });

});

?>
