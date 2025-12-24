<?php

namespace App\Http\Requests\Quadra;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuadraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255', 'unique:quadras,nome'],
            'tipo' => ['required', 'string', 'max:255'],
            'ativa' => ['sometimes', 'boolean'],
            'ordem' => ['nullable', 'integer'],
            'capacidade' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
