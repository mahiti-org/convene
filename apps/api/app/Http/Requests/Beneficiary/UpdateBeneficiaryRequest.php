<?php

namespace App\Http\Requests\Beneficiary;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBeneficiaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // Type, location, government ID and temporary_id are excluded: editing them would undermine
    // dedup and location scoping.
    public function rules(): array
    {
        return [
            'household_id' => ['sometimes', 'nullable', 'integer', 'exists:beneficiaries,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'dob' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:100'],
            'attributes_json' => ['sometimes', 'nullable', 'array'],
            'consent_given' => ['sometimes', 'boolean'],
        ];
    }
}
