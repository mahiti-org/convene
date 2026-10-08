<?php

namespace App\Http\Requests\MasterDefinition;

use Illuminate\Foundation\Http\FormRequest;

class DeactivateMasterDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
