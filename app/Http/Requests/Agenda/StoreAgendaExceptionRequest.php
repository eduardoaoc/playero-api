<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgendaExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data' => ['required', 'date_format:Y-m-d', 'unique:agenda_exceptions,data'],
            'hora_abertura' => ['required', 'date_format:H:i'],
            'hora_fechamento' => ['required', 'date_format:H:i', 'after:hora_abertura'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
