<?php

namespace App\Http\Requests\Discovery;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class SwipeActionRequest extends ApiFormRequest
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
            'target_user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::notIn(array_filter([$this->user()?->id])),
            ],
            'action' => ['required', Rule::in(['like', 'dislike', 'super_like'])],
        ];
    }
}
