<?php

namespace App\Models;

use App\Casts\TimeCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class AgendaException extends Model
{
    use HasFactory;

    protected $table = 'agenda_exceptions';

    protected $fillable = [
        'date',
        'opening_time',
        'closing_time',
        'is_closed',
        'reason',
        'created_by',
        'data',
        'hora_abertura',
        'hora_fechamento',
        'fechado',
        'motivo',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'opening_time' => TimeCast::class,
            'closing_time' => TimeCast::class,
            'is_closed' => 'boolean',
            'created_by' => 'integer',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getDataAttribute(): ?Carbon
    {
        return $this->getAttribute('date');
    }

    public function setDataAttribute($value): void
    {
        $this->attributes['date'] = $value;
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

    public function getFechadoAttribute(): bool
    {
        return (bool) ($this->attributes['is_closed'] ?? false);
    }

    public function setFechadoAttribute($value): void
    {
        $this->attributes['is_closed'] = (bool) $value;
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
