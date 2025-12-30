<?php

namespace App\Support;

use App\Models\AgendaConfig;

class AgendaConfigPresenter
{
    public static function make(AgendaConfig $config): array
    {
        return [
            'id' => $config->id,
            'opening_time' => $config->opening_time,
            'closing_time' => $config->closing_time,
            'slot_duration' => $config->slot_duration,
            'active_days' => $config->active_days,
            'timezone' => $config->timezone,
        ];
    }
}
