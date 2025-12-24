<?php

namespace App\Http\Requests\Reserva;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quadra_id' => [
                'required',
                'integer',
                Rule::exists('quadras', 'id')->where('ativa', true)->whereNull('deleted_at'),
            ],
            'data' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i'],
        ];
    }
}
