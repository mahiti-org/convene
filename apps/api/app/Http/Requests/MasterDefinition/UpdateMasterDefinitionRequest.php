<?php

namespace App\Http\Requests\MasterDefinition;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMasterDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
