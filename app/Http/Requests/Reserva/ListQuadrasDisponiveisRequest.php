<?php

namespace App\Http\Requests\Reserva;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class ListQuadrasDisponiveisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['sometimes', 'nullable', 'date_format:H:i'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if (! $this->filled('hora_inicio') && $this->filled('horario')) {
            $payload['hora_inicio'] = $this->input('horario');
        }

        if ($this->filled('data')) {
            $data = $this->input('data');
            if (is_string($data) && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $data)) {
                $parsed = Carbon::createFromFormat('d/m/Y', $data);
                $errors = Carbon::getLastErrors();
                $hasErrors = is_array($errors)
                    && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);

                if (! $hasErrors) {
                    $payload['data'] = $parsed->format('Y-m-d');
                }
            }
        }

        if (! empty($payload)) {
            $this->merge($payload);
        }
    }
}
