<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active
            && $this->user()?->role === 'nutritionist'
            && $this->user()?->nutritionist !== null;
    }

    public function rules(): array
    {
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            // The plan comes from the URL, never from the body.
            'nutrition_plan_id' => ['prohibited'],

            'name' => [$presence, 'string', 'max:150'],

            'meal_type' => [
                $presence,
                Rule::in(['breakfast', 'lunch', 'dinner', 'snack']),
            ],

            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],

            'calories' => [
                $presence,
                'numeric',
                'min:0',
                'max:999999.99',
                'decimal:0,2',
            ],
        ];
    }
}
