<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveNutritionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active
            && $this->user()?->role === 'nutritionist'
            && $this->user()?->nutritionist !== null;
    }

    public function rules(): array
    {
        $isCreate = $this->isMethod('POST');
        $presence = $isCreate ? 'required' : 'sometimes';

        return [
            'nutritionist_id' => ['prohibited'],

            // The athlete is chosen once, at creation time.
            'athlete_id' => $isCreate
                ? ['required', 'integer', 'exists:athletes,athlete_id']
                : ['prohibited'],

            'title' => [$presence, 'string', 'max:150'],

            'daily_calories_target' => [
                $presence,
                'numeric',
                'min:0',
                'max:999999.99',
                'decimal:0,2',
            ],

            'start_date' => [$presence, 'date_format:Y-m-d'],

            // On create both dates are sent, so the range can be validated here.
            // On update only one may be sent; the controller checks the merged range.
            'end_date' => $isCreate
                ? ['required', 'date_format:Y-m-d', 'after_or_equal:start_date']
                : ['sometimes', 'date_format:Y-m-d'],

            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
