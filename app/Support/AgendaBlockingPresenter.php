<?php

namespace App\Support;

use App\Models\AgendaBlocking;

class AgendaBlockingPresenter
{
    public static function make(AgendaBlocking $blocking): array
    {
        return [
            'id' => $blocking->id,
            'quadra_id' => $blocking->quadra_id,
            'data' => $blocking->data ? $blocking->data->format('Y-m-d') : null,
            'hora_inicio' => $blocking->hora_inicio,
            'hora_fim' => $blocking->hora_fim,
            'motivo' => $blocking->motivo,
        ];
    }
}
