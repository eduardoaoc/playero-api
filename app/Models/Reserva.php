<?php

namespace App\Models;

use App\Casts\TimeCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Reserva extends Model
{
    use HasFactory;

    public const STATUS_PENDENTE_PAGAMENTO = 'pendente_pagamento';
    public const STATUS_CONFIRMADA = 'confirmada';
    public const STATUS_CANCELADA = 'cancelada';
    public const STATUS_EXPIRADA = 'expirada';

    public const STATUSES = [
        self::STATUS_PENDENTE_PAGAMENTO,
        self::STATUS_CONFIRMADA,
        self::STATUS_CANCELADA,
        self::STATUS_EXPIRADA,
    ];

    public const ACTIVE_STATUSES = [
        self::STATUS_PENDENTE_PAGAMENTO,
        self::STATUS_CONFIRMADA,
    ];

    protected $fillable = [
        'user_id',
        'quadra_id',
        'data',
        'hora_inicio',
        'hora_fim',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'quadra_id' => 'integer',
            'data' => 'date:Y-m-d',
            'hora_inicio' => TimeCast::class,
            'hora_fim' => TimeCast::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quadra(): BelongsTo
    {
        return $this->belongsTo(Quadra::class);
    }

    public static function expirePendentes(Carbon $now): int
    {
        $today = $now->toDateString();
        $time = $now->format('H:i:s');

        return self::query()
            ->where('status', self::STATUS_PENDENTE_PAGAMENTO)
            ->where(function ($query) use ($today, $time) {
                $query->where('data', '<', $today)
                    ->orWhere(function ($subQuery) use ($today, $time) {
                        $subQuery->where('data', $today)
                            ->where('hora_inicio', '<=', $time);
                    });
            })
            ->update([
                'status' => self::STATUS_EXPIRADA,
                'updated_at' => now(),
            ]);
    }
}
