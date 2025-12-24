<?php

namespace App\Support;

use App\Models\Media;

class MediaPresenter
{
    public static function make(Media $media): array
    {
        return [
            'id' => $media->id,
            'url' => $media->url(),
            'path' => $media->path,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
        ];
    }
}
