<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends ApiFormRequest
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
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'age' => ['sometimes', 'required', 'integer', 'min:18', 'max:120'],
            'gender' => [
                'sometimes',
                'required',
                Rule::in(['male', 'female', 'non_binary', 'other']),
            ],
            'bio' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'walking_preferences' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'interests' => ['sometimes', 'required', 'array', 'min:1'],
            'interests.*' => ['required', 'integer', 'distinct', 'exists:interests,id'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'state' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'daily_step_goal' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
