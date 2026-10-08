<?php

namespace App\Http\Requests\FormResponse;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFormResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'values_json' => ['required', 'array'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
