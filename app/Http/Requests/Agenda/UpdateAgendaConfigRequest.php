<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgendaConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        if (array_key_exists('hora_abertura', $data) && ! array_key_exists('opening_time', $data)) {
            $data['opening_time'] = $data['hora_abertura'];
        }

        if (array_key_exists('hora_fechamento', $data) && ! array_key_exists('closing_time', $data)) {
            $data['closing_time'] = $data['hora_fechamento'];
        }

        if (array_key_exists('duracao_reserva_minutos', $data) && ! array_key_exists('slot_duration', $data)) {
            $data['slot_duration'] = $data['duracao_reserva_minutos'];
        }

        if (array_key_exists('dias_semana_ativos', $data) && ! array_key_exists('active_days', $data)) {
            $data['active_days'] = $data['dias_semana_ativos'];
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        return [
            'opening_time' => ['required', 'date_format:H:i'],
            'closing_time' => ['required', 'date_format:H:i', 'after:opening_time'],
            'slot_duration' => ['sometimes', 'integer', 'min:1'],
            'active_days' => ['sometimes', 'array', 'min:1'],
            'active_days.*' => ['integer', 'between:1,7', 'distinct'],
            'timezone' => ['sometimes', 'string', 'timezone'],
        ];
    }
}
