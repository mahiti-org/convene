<?php

namespace App\Http\Requests\SubProject;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
