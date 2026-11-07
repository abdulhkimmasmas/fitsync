<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MealLogController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/planned-meals', [
        MealLogController::class,
        'plannedMeals',
    ]);

    Route::get('/meal-logs/summary', [
        MealLogController::class,
        'summary',
    ]);

    Route::get('/meal-logs', [
        MealLogController::class,
        'index',
    ]);

    Route::post('/meal-logs', [
        MealLogController::class,
        'store',
    ]);

    Route::patch('/meal-logs/{id}', [
        MealLogController::class,
        'update',
    ])->whereNumber('id');

    Route::delete('/meal-logs/{id}', [
        MealLogController::class,
        'destroy',
    ])->whereNumber('id');

    Route::get('/nutritionist/athletes/{athleteId}/meal-logs', [
        MealLogController::class,
        'nutritionistIndex',
    ])->whereNumber('athleteId');
});