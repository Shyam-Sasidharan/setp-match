<?php

namespace App\Http\Requests\Fitness;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class FitnessConnectRequest extends ApiFormRequest
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
            'provider_user_id' => ['nullable', 'string', 'max:255'],
            'access_token' => ['nullable', 'string'],
            'refresh_token' => ['nullable', 'string'],
            'token_expires_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
