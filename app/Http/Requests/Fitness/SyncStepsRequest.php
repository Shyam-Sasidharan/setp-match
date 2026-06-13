<?php

namespace App\Http\Requests\Fitness;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class SyncStepsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::in(['google_fit', 'apple_health'])],
            'date' => ['required', 'date_format:Y-m-d'],
            'steps' => ['required', 'integer', 'min:0'],
            'goal_steps' => ['required', 'integer', 'min:1'],
            'distance_km' => ['nullable', 'numeric', 'min:0'],
            'calories' => ['nullable', 'numeric', 'min:0'],
            'heart_rate' => ['nullable', 'integer', 'min:0', 'max:300'],
            'active_minutes' => ['nullable', 'integer', 'min:0'],
            'source_data' => ['nullable', 'array'],
        ];
    }
}
