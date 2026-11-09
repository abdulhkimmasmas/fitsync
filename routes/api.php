<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MealLogController;
use App\Http\Controllers\Api\NutritionistController;
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

    Route::prefix('nutritionist')->group(function () {
        Route::get('/athletes', [NutritionistController::class, 'athletes']);

        Route::get('/athletes/{athleteId}/meal-logs', [
            MealLogController::class,
            'nutritionistIndex',
        ])->whereNumber('athleteId');

        Route::get('/plans', [NutritionistController::class, 'plansIndex']);
        Route::post('/plans', [NutritionistController::class, 'plansStore']);

        Route::get('/plans/{id}', [NutritionistController::class, 'plansShow'])
            ->whereNumber('id');
        Route::patch('/plans/{id}', [NutritionistController::class, 'plansUpdate'])
            ->whereNumber('id');
        Route::delete('/plans/{id}', [NutritionistController::class, 'plansDestroy'])
            ->whereNumber('id');

        Route::post('/plans/{planId}/meals', [
            NutritionistController::class,
            'mealsStore',
        ])->whereNumber('planId');

        Route::patch('/meals/{id}', [NutritionistController::class, 'mealsUpdate'])
            ->whereNumber('id');
        Route::delete('/meals/{id}', [NutritionistController::class, 'mealsDestroy'])
            ->whereNumber('id');
    });
});