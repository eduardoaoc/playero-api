<?php

namespace App\Models;

use App\Casts\TimeCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgendaException extends Model
{
    use HasFactory;

    protected $table = 'agenda_exceptions';

    protected $fillable = [
        'data',
        'hora_abertura',
        'hora_fechamento',
        'motivo',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date:Y-m-d',
            'hora_abertura' => TimeCast::class,
            'hora_fechamento' => TimeCast::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $exception): void {
            $attributes = $exception->getAttributes();

            if (array_key_exists('data', $attributes)) {
                $exception->attributes['date'] = $attributes['data'];
            }

            if (array_key_exists('hora_abertura', $attributes)) {
                $exception->attributes['open_time'] = $attributes['hora_abertura'];
            }

            if (array_key_exists('hora_fechamento', $attributes)) {
                $exception->attributes['close_time'] = $attributes['hora_fechamento'];
            }
        });
    }
}
