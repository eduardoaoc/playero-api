<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quadra extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'nome',
        'tipo',
        'ativa',
        'ordem',
        'capacidade',
    ];

    protected function casts(): array
    {
        return [
            'ativa' => 'boolean',
            'ordem' => 'integer',
            'capacidade' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('ativa', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('ordem')->orderBy('nome');
    }
}
