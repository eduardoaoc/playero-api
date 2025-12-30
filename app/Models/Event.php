<?php

namespace App\Models;

use App\Casts\TimeCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'start_time',
        'end_time',
        'type',
        'visibility',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'start_time' => TimeCast::class,
            'end_time' => TimeCast::class,
        ];
    }
}
