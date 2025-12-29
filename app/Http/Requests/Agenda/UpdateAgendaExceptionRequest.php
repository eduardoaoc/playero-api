<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgendaExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $exceptionId = $this->route('id');

        return [
            'data' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique('agenda_exceptions', 'data')->ignore($exceptionId),
            ],
            'hora_abertura' => ['required', 'date_format:H:i'],
            'hora_fechamento' => ['required', 'date_format:H:i', 'after:hora_abertura'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
