<?php

namespace App\Http\Requests\Quadra;

use Illuminate\Foundation\Http\FormRequest;

class ToggleQuadraStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ativa' => ['required', 'boolean'],
        ];
    }
}
