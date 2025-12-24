<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    public const SUPER_ADMIN = 'SUPER_ADMIN';
    public const ADMIN = 'ADMIN';
    public const CLIENTE = 'CLIENTE';

    public const ALL = [
        self::SUPER_ADMIN,
        self::ADMIN,
        self::CLIENTE,
    ];

    protected $fillable = [
        'name',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
