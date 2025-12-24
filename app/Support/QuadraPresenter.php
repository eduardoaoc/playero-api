<?php

namespace App\Support;

use App\Models\Quadra;

class QuadraPresenter
{
    public static function make(Quadra $quadra): array
    {
        return [
            'id' => $quadra->id,
            'nome' => $quadra->nome,
            'tipo' => $quadra->tipo,
            'ativa' => (bool) $quadra->ativa,
            'ordem' => $quadra->ordem,
            'capacidade' => $quadra->capacidade,
        ];
    }
}
