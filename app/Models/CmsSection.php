<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'section',
        'order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CmsField::class);
    }
}
