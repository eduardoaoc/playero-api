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

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        if (array_key_exists('data', $data) && ! array_key_exists('date', $data)) {
            $data['date'] = $data['data'];
        }

        if (array_key_exists('fechado', $data) && ! array_key_exists('is_closed', $data)) {
            $data['is_closed'] = $data['fechado'];
        }

        if (array_key_exists('hora_abertura', $data) && ! array_key_exists('opening_time', $data)) {
            $data['opening_time'] = $data['hora_abertura'];
        }

        if (array_key_exists('hora_fechamento', $data) && ! array_key_exists('closing_time', $data)) {
            $data['closing_time'] = $data['hora_fechamento'];
        }

        if (array_key_exists('motivo', $data) && ! array_key_exists('reason', $data)) {
            $data['reason'] = $data['motivo'];
        }

        if (! array_key_exists('is_closed', $data)) {
            $data['is_closed'] = false;
        }

        if ($data['is_closed']) {
            $data['opening_time'] = null;
            $data['closing_time'] = null;
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        $exceptionId = $this->route('id');

        return [
            'date' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique('agenda_exceptions', 'date')->ignore($exceptionId),
            ],
            'is_closed' => ['sometimes', 'boolean'],
            'opening_time' => ['nullable', 'required_if:is_closed,false', 'date_format:H:i'],
            'closing_time' => ['nullable', 'required_if:is_closed,false', 'date_format:H:i', 'after:opening_time'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
