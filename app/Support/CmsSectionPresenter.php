<?php

namespace App\Support;

use App\Models\CmsSection;

class CmsSectionPresenter
{
    public static function make(CmsSection $section): array
    {
        $fields = $section->relationLoaded('fields')
            ? $section->fields
            : $section->fields()->get();

        return [
            'id' => $section->id,
            'section' => $section->section,
            'order' => (int) $section->order,
            'active' => (bool) $section->active,
            'fields' => $fields
                ->map(fn ($field) => CmsFieldPresenter::make($field))
                ->values()
                ->all(),
        ];
    }
}
