<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'meal_id' => $this->meal_id,
            'nutrition_plan_id' => $this->nutrition_plan_id,
            'name' => $this->name,
            'meal_type' => $this->meal_type,
            'description' => $this->description,
            'calories' => $this->calories === null ? null : (float) $this->calories,
        ];
    }
}
