<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NutritionPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'nutrition_plan_id' => $this->nutrition_plan_id,
            'athlete_id' => $this->athlete_id,
            'nutritionist_id' => $this->nutritionist_id,
            'title' => $this->title,
            'daily_calories_target' => (float) $this->daily_calories_target,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_active' => (bool) $this->is_active,
            'meals' => MealResource::collection($this->whenLoaded('meals')),
        ];
    }
}
