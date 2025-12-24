<?php

namespace App\Support;

use App\Models\CmsField;

class CmsFieldPresenter
{
    public static function make(CmsField $field): array
    {
        $media = null;
        if ($field->relationLoaded('media') && $field->media) {
            $media = MediaPresenter::make($field->media);
        }

        return [
            'id' => $field->id,
            'key' => $field->key,
            'type' => $field->type,
            'value' => $field->value,
            'order' => (int) $field->order,
            'active' => (bool) $field->active,
            'media' => $media,
        ];
    }
}
