<?php

namespace App\Models;

use App\Casts\TimeCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgendaBlocking extends Model
{
    use HasFactory;

    protected $fillable = [
        'quadra_id',
        'data',
        'hora_inicio',
        'hora_fim',
        'motivo',
    ];

    protected function casts(): array
    {
        return [
            'quadra_id' => 'integer',
            'data' => 'date:Y-m-d',
            'hora_inicio' => TimeCast::class,
            'hora_fim' => TimeCast::class,
        ];
    }

    public function quadra(): BelongsTo
    {
        return $this->belongsTo(Quadra::class);
    }
}
