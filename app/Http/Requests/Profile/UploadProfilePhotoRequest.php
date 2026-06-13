<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\ApiFormRequest;

class UploadProfilePhotoRequest extends ApiFormRequest
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
            'profile_photo' => ['required', 'image', 'max:5120'],
        ];
    }
}
