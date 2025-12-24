<?php

namespace App\Http\Requests\Reserva;

use App\Models\Reserva;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListReservasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data' => ['sometimes', 'date_format:Y-m-d'],
            'quadra_id' => ['sometimes', 'integer', 'exists:quadras,id'],
            'status' => ['sometimes', 'string', Rule::in(Reserva::STATUSES)],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
        ];
    }
}
