<?php

namespace App\Http\Requests\FormDefinition;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFormDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // code/periodicity are not editable. Published forms get a new draft version instead.
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'beneficiary_type_id' => ['sometimes', 'nullable', 'integer', 'exists:beneficiary_types,id'],
            'activity_id' => ['sometimes', 'nullable', 'integer', 'exists:activities,id'],
            'geography_level' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'schema_json' => ['sometimes', 'array'],
        ];
    }
}
