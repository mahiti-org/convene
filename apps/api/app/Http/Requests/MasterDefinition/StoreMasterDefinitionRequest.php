<?php

namespace App\Http\Requests\MasterDefinition;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMasterDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'max:100'],
            'code' => [
                'required', 'string', 'max:100',
                Rule::unique('master_definitions', 'code')->where('category', $this->input('category')),
            ],
            'label' => ['required', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
