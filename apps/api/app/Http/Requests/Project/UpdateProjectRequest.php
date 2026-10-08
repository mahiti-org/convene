<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // program_id intentionally not editable; moving a project between programs is a
    // structural change (same reasoning as GeographyNode's no-reparenting policy).
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'project_manager_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
