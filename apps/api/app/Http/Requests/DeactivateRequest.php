<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Shared "reason" validation for deactivate endpoints. */
class DeactivateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
