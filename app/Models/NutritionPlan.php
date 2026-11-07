<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NutritionPlan extends Model
{
    protected $primaryKey = 'nutrition_plan_id';

    protected $fillable = [
        'athlete_id',
        'nutritionist_id',
        'title',
        'daily_calories_target',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'daily_calories_target' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class, 'athlete_id', 'athlete_id');
    }

    public function nutritionist(): BelongsTo
    {
        return $this->belongsTo(Nutritionist::class, 'nutritionist_id', 'nutritionist_id');
    }

    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class, 'nutrition_plan_id', 'nutrition_plan_id');
    }
}