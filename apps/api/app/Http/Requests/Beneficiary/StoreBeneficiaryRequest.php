<?php

namespace App\Http\Requests\Beneficiary;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBeneficiaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'beneficiary_type_id' => ['required', 'integer', 'exists:beneficiary_types,id'],
            'household_id' => ['nullable', 'integer', 'exists:beneficiaries,id'],
            'geography_node_id' => ['required', 'integer', 'exists:geography_nodes,id'],
            'name' => ['required', 'string', 'max:255'],
            'dob' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', 'string', 'max:100'],
            'government_id_type' => ['nullable', 'string', 'max:50'],
            'government_id_value' => ['nullable', 'string', 'max:100', 'required_with:government_id_type'],
            'government_id_checksum_valid' => ['sometimes', 'boolean'],
            'temporary_id' => ['nullable', 'string', 'max:100'],
            'attributes_json' => ['nullable', 'array'],
            'consent_given' => ['sometimes', 'boolean'],
            'created_channel' => ['sometimes', Rule::in(['web', 'mobile'])],
            'client_created_at' => ['sometimes', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Requires a government ID or a temporary ID.
            if (empty($this->input('government_id_value')) && empty($this->input('temporary_id'))) {
                $validator->errors()->add(
                    'government_id_value',
                    'Either a government ID or a temporary ID is required.',
                );
            }
        });
    }
}
