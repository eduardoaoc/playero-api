<?php

namespace App\Support;

use App\Models\AgendaException;

class AgendaExceptionPresenter
{
    public static function make(AgendaException $exception): array
    {
        $isClosed = (bool) $exception->is_closed
            || $exception->opening_time === null
            || $exception->closing_time === null;

        return [
            'id' => $exception->id,
            'date' => $exception->date ? $exception->date->format('Y-m-d') : null,
            'opening_time' => $isClosed ? null : $exception->opening_time,
            'closing_time' => $isClosed ? null : $exception->closing_time,
            'is_closed' => $isClosed,
            'reason' => $exception->reason,
        ];
    }
}
