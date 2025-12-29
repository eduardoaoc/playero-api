<?php

namespace App\Support;

use App\Models\AgendaException;

class AgendaExceptionPresenter
{
    public static function make(AgendaException $exception): array
    {
        return [
            'id' => $exception->id,
            'data' => $exception->data ? $exception->data->format('Y-m-d') : null,
            'hora_abertura' => $exception->hora_abertura,
            'hora_fechamento' => $exception->hora_fechamento,
            'motivo' => $exception->motivo,
        ];
    }
}
