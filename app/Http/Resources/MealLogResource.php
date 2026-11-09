<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'meal_log_id' => $this->meal_log_id,
            'athlete_id' => $this->athlete_id,
            'meal_id' => $this->meal_id,
            'name' => $this->name,
            'meal_type' => $this->meal_type,
            'calories' => (float) $this->calories,
            'consumed_at' => $this->consumed_at?->toIso8601String(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
