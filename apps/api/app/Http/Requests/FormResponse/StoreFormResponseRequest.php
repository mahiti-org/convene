<?php

namespace App\Http\Requests\FormResponse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'form_definition_id' => ['required', 'integer', 'exists:form_definitions,id'],
            'beneficiary_id' => ['nullable', 'integer', 'exists:beneficiaries,id'],
            'activity_id' => ['nullable', 'integer', 'exists:activities,id'],
            'geography_node_id' => ['required', 'integer', 'exists:geography_nodes,id'],
            'values_json' => ['required', 'array'],
            'channel' => ['sometimes', Rule::in(['web', 'mobile'])],
            'client_created_at' => ['sometimes', 'date'],
        ];
    }
}
