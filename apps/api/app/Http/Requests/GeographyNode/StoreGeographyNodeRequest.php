<?php

namespace App\Http\Requests\GeographyNode;

use Illuminate\Foundation\Http\FormRequest;

class StoreGeographyNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorized in the controller via Gate::authorize('perform', ...), which needs the request
        // channel and target node.
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', 'exists:geography_nodes,id'],
            'level' => ['required', 'integer', 'min:0', 'max:255'],
            'label' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255'],
        ];
    }
}
