<?php

namespace App\Http\Requests\Quadra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuadraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $quadraId = $this->route('id');

        return [
            'nome' => ['sometimes', 'string', 'max:255', Rule::unique('quadras', 'nome')->ignore($quadraId)],
            'tipo' => ['sometimes', 'string', 'max:255'],
            'ordem' => ['sometimes', 'nullable', 'integer'],
            'capacidade' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
