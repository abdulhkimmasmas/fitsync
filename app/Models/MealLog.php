<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealLog extends Model
{
    protected $primaryKey = 'meal_log_id';

    protected $fillable = [
        'meal_id',
        'name',
        'meal_type',
        'calories',
        'consumed_at',
        'notes',
    ];

    protected $casts = [
        'calories' => 'decimal:2',
        'consumed_at' => 'datetime',
    ];

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(
            Athlete::class,
            'athlete_id',
            'athlete_id'
        );
    }

    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class, 'meal_id', 'meal_id');
    }
}