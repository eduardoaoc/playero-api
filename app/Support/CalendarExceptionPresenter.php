<?php

namespace App\Support;

use App\Models\AgendaException;

class CalendarExceptionPresenter
{
    public static function make(AgendaException $exception): array
    {
        $isClosed = (bool) $exception->is_closed
            || $exception->opening_time === null
            || $exception->closing_time === null;

        return [
            'id' => $exception->id,
            'date' => $exception->date ? $exception->date->format('Y-m-d') : null,
            'is_closed' => $isClosed,
            'open_time' => $isClosed ? null : $exception->opening_time,
            'close_time' => $isClosed ? null : $exception->closing_time,
            'reason' => $exception->reason,
            'created_by' => $exception->created_by,
        ];
    }
}
