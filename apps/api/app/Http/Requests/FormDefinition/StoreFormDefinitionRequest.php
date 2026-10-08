<?php

namespace App\Http\Requests\FormDefinition;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100', Rule::unique('form_definitions', 'code')->where('version', 1)],
            'name' => ['required', 'string', 'max:255'],
            'beneficiary_type_id' => ['nullable', 'integer', 'exists:beneficiary_types,id'],
            'activity_id' => ['nullable', 'integer', 'exists:activities,id'],
            'geography_level' => ['nullable', 'integer', 'min:0', 'max:255'],
            'periodicity' => ['required', Rule::in(['one_time', 'monthly', 'quarterly', 'half_yearly', 'annual', 'ad_hoc'])],
            'schema_json' => ['nullable', 'array'],
        ];
    }
}
