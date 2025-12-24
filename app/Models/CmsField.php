<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsField extends Model
{
    use HasFactory;

    public const TYPE_TEXT = 'text';
    public const TYPE_IMAGE = 'image';
    public const TYPE_TEXTAREA = 'textarea';

    public const TYPES = [
        self::TYPE_TEXT,
        self::TYPE_IMAGE,
        self::TYPE_TEXTAREA,
    ];

    protected $fillable = [
        'cms_section_id',
        'media_id',
        'key',
        'value',
        'type',
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

    public function section(): BelongsTo
    {
        return $this->belongsTo(CmsSection::class, 'cms_section_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
