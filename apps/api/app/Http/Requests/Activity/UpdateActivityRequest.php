<?php

namespace App\Http\Requests\Activity;

use Illuminate\Foundation\Http\FormRequest;

class UpdateActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // project_id / parent_activity_id intentionally not editable: structural, same
    // no-reparenting policy as GeographyNode/Project.
    public function rules(): array
    {
        return [
            'beneficiary_type_id' => ['sometimes', 'nullable', 'integer', 'exists:beneficiary_types,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
