<?php

namespace App\Support;

use App\Models\Reserva;

class ReservaPresenter
{
    public static function make(Reserva $reserva): array
    {
        return [
            'id' => $reserva->id,
            'user_id' => $reserva->user_id,
            'quadra_id' => $reserva->quadra_id,
            'data' => $reserva->data ? $reserva->data->format('Y-m-d') : null,
            'hora_inicio' => $reserva->hora_inicio,
            'hora_fim' => $reserva->hora_fim,
            'status' => $reserva->status,
        ];
    }
}
