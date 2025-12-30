<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgendaBlockingRequest extends FormRequest
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

        if (array_key_exists('hora_inicio', $data) && ! array_key_exists('start_time', $data)) {
            $data['start_time'] = $data['hora_inicio'];
        }

        if (array_key_exists('hora_fim', $data) && ! array_key_exists('end_time', $data)) {
            $data['end_time'] = $data['hora_fim'];
        }

        if (array_key_exists('motivo', $data) && ! array_key_exists('reason', $data)) {
            $data['reason'] = $data['motivo'];
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        return [
            'quadra_id' => ['nullable', 'integer', 'exists:quadras,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'date_format:H:i', 'required_with:end_time'],
            'end_time' => ['nullable', 'date_format:H:i', 'required_with:start_time', 'after:start_time'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
