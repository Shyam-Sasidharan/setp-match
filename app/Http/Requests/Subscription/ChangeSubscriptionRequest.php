<?php

namespace App\Http\Requests\Subscription;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class ChangeSubscriptionRequest extends ApiFormRequest
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
            'plan' => ['required', Rule::in(['free', 'gold', 'premium'])],
            'provider' => ['nullable', 'string', 'max:50'],
            'provider_subscription_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
