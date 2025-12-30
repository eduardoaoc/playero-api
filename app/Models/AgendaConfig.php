<?php

namespace App\Models;

use App\Casts\TimeCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgendaConfig extends Model
{
    use HasFactory;

    protected $table = 'agenda_config';

    protected $fillable = [
        'opening_time',
        'closing_time',
        'slot_duration',
        'active_days',
        'timezone',
        'hora_abertura',
        'hora_fechamento',
        'duracao_reserva_minutos',
        'dias_semana_ativos',
    ];

    protected function casts(): array
    {
        return [
            'opening_time' => TimeCast::class,
            'closing_time' => TimeCast::class,
            'slot_duration' => 'integer',
            'active_days' => 'array',
        ];
    }

    public function getHoraAberturaAttribute(): ?string
    {
        return $this->getAttribute('opening_time');
    }

    public function setHoraAberturaAttribute($value): void
    {
        $this->attributes['opening_time'] = $value;
    }

    public function getHoraFechamentoAttribute(): ?string
    {
        return $this->getAttribute('closing_time');
    }

    public function setHoraFechamentoAttribute($value): void
    {
        $this->attributes['closing_time'] = $value;
    }

    public function getDuracaoReservaMinutosAttribute(): ?int
    {
        return $this->getAttribute('slot_duration');
    }

    public function setDuracaoReservaMinutosAttribute($value): void
    {
        $this->attributes['slot_duration'] = $value;
    }

    public function getDiasSemanaAtivosAttribute(): array
    {
        return $this->getAttribute('active_days') ?? [];
    }

    public function setDiasSemanaAtivosAttribute($value): void
    {
        $this->attributes['active_days'] = $value;
    }
}
