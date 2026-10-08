<?php

namespace App\Http\Requests\GeographyNode;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGeographyNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
