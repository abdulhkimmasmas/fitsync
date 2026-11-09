<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meal extends Model
{
    protected $primaryKey = 'meal_id';

    protected $fillable = [
        'nutrition_plan_id',
        'name',
        'meal_type',
        'description',
        'calories',
    ];

    protected $casts = [
        'calories' => 'decimal:2',
    ];

    public function mealLogs(): HasMany
    {
        return $this->hasMany(MealLog::class, 'meal_id', 'meal_id');
    }

    public function nutritionPlan(): BelongsTo
    {
        return $this->belongsTo(
            NutritionPlan::class,
            'nutrition_plan_id',
            'nutrition_plan_id'
        );
    }
}