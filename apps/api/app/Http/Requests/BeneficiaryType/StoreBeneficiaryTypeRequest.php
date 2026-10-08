<?php

namespace App\Http\Requests\BeneficiaryType;

use App\Models\BeneficiaryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBeneficiaryTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class' => ['required', Rule::in([BeneficiaryType::CLASS_INSTITUTIONAL, BeneficiaryType::CLASS_INDIVIDUAL])],
            'code' => ['required', 'string', 'max:100', 'unique:beneficiary_types,code'],
            'label' => ['required', 'string', 'max:255'],
            'attribute_schema' => ['nullable', 'array'],
            'attribute_schema.*.key' => ['required_with:attribute_schema', 'string'],
            'attribute_schema.*.label' => ['required_with:attribute_schema', 'string'],
            'attribute_schema.*.data_type' => ['required_with:attribute_schema', 'string'],
            'attribute_schema.*.required' => ['sometimes', 'boolean'],
        ];
    }
}
