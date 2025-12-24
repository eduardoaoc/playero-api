<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgendaConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hora_abertura' => ['required', 'date_format:H:i'],
            'hora_fechamento' => ['required', 'date_format:H:i', 'after:hora_abertura'],
            'duracao_reserva_minutos' => ['required', 'integer', 'min:1'],
            'dias_semana_ativos' => ['required', 'array', 'min:1'],
            'dias_semana_ativos.*' => ['integer', 'between:1,7', 'distinct'],
            'timezone' => ['sometimes', 'string', 'timezone'],
        ];
    }
}
