<?php

namespace App\Models;

use App\Casts\TimeCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class AgendaBlocking extends Model
{
    use HasFactory;

    protected $fillable = [
        'quadra_id',
        'date',
        'start_time',
        'end_time',
        'reason',
        'data',
        'hora_inicio',
        'hora_fim',
        'motivo',
    ];

    protected function casts(): array
    {
        return [
            'quadra_id' => 'integer',
            'date' => 'date:Y-m-d',
            'start_time' => TimeCast::class,
            'end_time' => TimeCast::class,
        ];
    }

    public function quadra(): BelongsTo
    {
        return $this->belongsTo(Quadra::class);
    }

    public function getDataAttribute(): ?Carbon
    {
        return $this->getAttribute('date');
    }

    public function setDataAttribute($value): void
    {
        $this->attributes['date'] = $value;
    }

    public function getHoraInicioAttribute(): ?string
    {
        return $this->getAttribute('start_time');
    }

    public function setHoraInicioAttribute($value): void
    {
        $this->attributes['start_time'] = $value;
    }

    public function getHoraFimAttribute(): ?string
    {
        return $this->getAttribute('end_time');
    }

    public function setHoraFimAttribute($value): void
    {
        $this->attributes['end_time'] = $value;
    }

    public function getMotivoAttribute(): ?string
    {
        return $this->getAttribute('reason');
    }

    public function setMotivoAttribute($value): void
    {
        $this->attributes['reason'] = $value;
    }
}
