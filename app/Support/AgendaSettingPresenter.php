<?php

namespace App\Support;

use App\Models\AgendaSetting;

class AgendaSettingPresenter
{
    public static function make(AgendaSetting $setting): array
    {
        return [
            'id' => $setting->id,
            'hora_abertura' => $setting->hora_abertura,
            'hora_fechamento' => $setting->hora_fechamento,
            'duracao_reserva_minutos' => $setting->duracao_reserva_minutos,
            'dias_semana_ativos' => $setting->dias_semana_ativos,
            'timezone' => $setting->timezone,
        ];
    }
}
