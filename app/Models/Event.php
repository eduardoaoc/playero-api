<?php

namespace App\Models;

use App\Casts\TimeCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_ANIVERSARIO = 'aniversario';
    public const TYPE_VIP = 'vip';
    public const TYPE_CORPORATIVO = 'corporativo';
    public const TYPE_GASTRONOMICO = 'gastronomico';
    public const TYPE_MUSICA_AO_VIVO = 'musica_ao_vivo';
    public const TYPE_TORNEIO = 'torneio';
    public const TYPE_OUTRO = 'outro';

    public const TYPES = [
        self::TYPE_ANIVERSARIO,
        self::TYPE_VIP,
        self::TYPE_CORPORATIVO,
        self::TYPE_GASTRONOMICO,
        self::TYPE_MUSICA_AO_VIVO,
        self::TYPE_TORNEIO,
        self::TYPE_OUTRO,
    ];

    public const VISIBILITY_PUBLIC = 'publico';
    public const VISIBILITY_PRIVATE = 'privado';

    public const VISIBILITIES = [
        self::VISIBILITY_PUBLIC,
        self::VISIBILITY_PRIVATE,
    ];

    public const STATUS_ACTIVE = 'ativo';
    public const STATUS_INACTIVE = 'inativo';
    public const STATUS_CANCELED = 'cancelado';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_CANCELED,
    ];

    protected $fillable = [
        'name',
        'date',
        'start_time',
        'end_time',
        'type',
        'location',
        'max_people',
        'visibility',
        'is_paid',
        'status',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'start_time' => TimeCast::class,
            'end_time' => TimeCast::class,
            'is_paid' => 'boolean',
            'max_people' => 'integer',
            'created_by' => 'integer',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
