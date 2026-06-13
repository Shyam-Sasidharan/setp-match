<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class ChallengeInviteRequest extends ApiFormRequest
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
            'opponent_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::notIn(array_filter([$this->user()?->id])),
            ],
            'challenge_type' => ['required', Rule::in(['steps', 'distance'])],
            'target_value' => ['required', 'numeric', 'gt:0'],
            'start_date' => ['required', 'date', 'before_or_equal:end_date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
