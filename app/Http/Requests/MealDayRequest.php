<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MealDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active
            && in_array(
                $this->user()?->role,
                ['athlete', 'nutritionist'],
                true
            );
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}