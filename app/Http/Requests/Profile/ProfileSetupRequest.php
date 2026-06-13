<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class ProfileSetupRequest extends ApiFormRequest
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
            'full_name' => ['required', 'string', 'max:255'],
            'age' => ['required', 'integer', 'min:18', 'max:120'],
            'gender' => ['required', Rule::in(['male', 'female', 'non_binary', 'other'])],
            'bio' => ['nullable', 'string', 'max:2000'],
            'walking_preferences' => ['nullable', 'string', 'max:2000'],
            'interests' => ['required', 'array', 'min:1'],
            'interests.*' => ['required', 'integer', 'distinct', 'exists:interests,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'daily_step_goal' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
