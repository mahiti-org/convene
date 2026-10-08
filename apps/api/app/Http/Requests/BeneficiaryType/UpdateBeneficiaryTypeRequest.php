<?php

namespace App\Http\Requests\BeneficiaryType;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBeneficiaryTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'string', 'max:255'],
            'attribute_schema' => ['sometimes', 'nullable', 'array'],
            'attribute_schema.*.key' => ['required_with:attribute_schema', 'string'],
            'attribute_schema.*.label' => ['required_with:attribute_schema', 'string'],
            'attribute_schema.*.data_type' => ['required_with:attribute_schema', 'string'],
            'attribute_schema.*.required' => ['sometimes', 'boolean'],
        ];
    }
}
