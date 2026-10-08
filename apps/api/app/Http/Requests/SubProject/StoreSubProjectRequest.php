<?php

namespace App\Http\Requests\SubProject;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
