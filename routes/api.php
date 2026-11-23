<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BorrowController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\PlaceController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['status' => 'ok', 'service' => 'school-inventory-api']);

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/signup', [AuthController::class, 'signup']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::patch('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/notifications', [DashboardController::class, 'notifications']);

    Route::get('/items/meta/units', [ItemController::class, 'metaUnits']);
    Route::patch('/items/{item}/restore', [ItemController::class, 'restore']);
    Route::apiResource('items', ItemController::class);

    Route::apiResource('transactions', TransactionController::class)->only(['index', 'store']);

    Route::get('/borrows', [BorrowController::class, 'index']);
    Route::post('/borrows', [BorrowController::class, 'store']);
    Route::post('/borrows/{borrow}/return', [BorrowController::class, 'returnItem']);

    Route::apiResource('categories', CategoryController::class)->except(['show']);
    Route::apiResource('places', PlaceController::class)->except(['show']);
    Route::get('/reports', [ReportController::class, 'index']);

    Route::post('/users/{user}/toggle', [UserController::class, 'toggle']);
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);
    Route::apiResource('users', UserController::class)->except(['show', 'destroy']);

    Route::get('/activity-logs', [ActivityLogController::class, 'index']);

    Route::get('/settings', [SettingsController::class, 'show']);
    Route::patch('/settings', [SettingsController::class, 'update']);
    Route::post('/settings/apply-threshold', [SettingsController::class, 'applyThreshold']);
});