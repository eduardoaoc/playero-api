<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgendaBlockingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quadra_id' => ['nullable', 'integer', 'exists:quadras,id'],
            'data' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['nullable', 'date_format:H:i', 'required_with:hora_fim'],
            'hora_fim' => ['nullable', 'date_format:H:i', 'required_with:hora_inicio', 'after:hora_inicio'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
