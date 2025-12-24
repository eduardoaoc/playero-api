<?php

namespace App\Models;

use App\Casts\TimeCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgendaSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'hora_abertura',
        'hora_fechamento',
        'duracao_reserva_minutos',
        'dias_semana_ativos',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'hora_abertura' => TimeCast::class,
            'hora_fechamento' => TimeCast::class,
            'duracao_reserva_minutos' => 'integer',
            'dias_semana_ativos' => 'array',
        ];
    }
}
