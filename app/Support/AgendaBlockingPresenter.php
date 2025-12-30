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
            'date' => $blocking->date ? $blocking->date->format('Y-m-d') : null,
            'start_time' => $blocking->start_time,
            'end_time' => $blocking->end_time,
            'reason' => $blocking->reason,
        ];
    }
}
