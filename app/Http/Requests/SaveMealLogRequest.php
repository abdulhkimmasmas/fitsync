<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMealLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active
            && $this->user()?->role === 'athlete'
            && $this->user()?->athlete !== null;
    }

    public function rules(): array
    {
        $presence = $this->isMethod('POST')
            ? 'required'
            : 'sometimes';

        return [
            'athlete_id' => ['prohibited'],
            'user_id' => ['prohibited'],

            'meal_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('meals', 'meal_id')->whereIn(
                    'nutrition_plan_id',
                    $this->user()->athlete->nutritionPlans()
                        ->select('nutrition_plan_id')
                ),
            ],

            'name' => [
                $presence,
                'required',
                'string',
                'max:150',
            ],

            'meal_type' => [
                $presence,
                'required',
                Rule::in([
                    'breakfast',
                    'lunch',
                    'dinner',
                    'snack',
                ]),
            ],

            'calories' => [
                $presence,
                'required',
                'numeric',
                'min:0',
                'max:999999.99',
                'decimal:0,2',
            ],

            'consumed_at' => [
                $presence,
                'required',
                'date_format:Y-m-d\TH:i:sP',
                'before_or_equal:now',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}