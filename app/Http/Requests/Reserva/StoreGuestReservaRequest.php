<?php

namespace App\Http\Requests\Reserva;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreGuestReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user' => ['required', 'array'],
            'user.name' => ['required', 'string', 'min:2', 'max:255'],
            'user.email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'user.password' => ['required', 'string', 'min:8', 'confirmed'],
            'user.password_confirmation' => ['required', 'string', 'min:8'],
            'reserva' => ['required', 'array'],
            'reserva.quadra_id' => [
                'required',
                'integer',
                Rule::exists('quadras', 'id')->where('ativa', true)->whereNull('deleted_at'),
            ],
            'reserva.data' => ['required', 'date_format:Y-m-d'],
            'reserva.hora_inicio' => ['required', 'date_format:H:i'],
            'reserva.hora_fim' => ['required', 'date_format:H:i'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $reserva = $this->input('reserva');

        if (! is_array($reserva)) {
            return;
        }

        $payload = [];

        if (! array_key_exists('hora_inicio', $reserva) && ! empty($reserva['start_time'])) {
            $payload['hora_inicio'] = $reserva['start_time'];
        }

        if (! array_key_exists('hora_fim', $reserva) && ! empty($reserva['end_time'])) {
            $payload['hora_fim'] = $reserva['end_time'];
        }

        if (! array_key_exists('data', $reserva) && ! empty($reserva['date'])) {
            $payload['data'] = $reserva['date'];
        }

        if (array_key_exists('data', $payload) || array_key_exists('data', $reserva)) {
            $dateValue = $payload['data'] ?? $reserva['data'] ?? null;

            if (is_string($dateValue) && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateValue)) {
                $parsed = Carbon::createFromFormat('d/m/Y', $dateValue);
                $errors = Carbon::getLastErrors();
                $hasErrors = is_array($errors)
                    && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);

                if (! $hasErrors) {
                    $payload['data'] = $parsed->format('Y-m-d');
                }
            }
        }

        if (empty($payload)) {
            return;
        }

        $this->merge([
            'reserva' => array_merge($reserva, $payload),
        ]);
    }
}
