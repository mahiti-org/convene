<?php

namespace App\Http\Requests\GeographyNode;

use Illuminate\Foundation\Http\FormRequest;

class DeactivateGeographyNodeRequest extends FormRequest
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
