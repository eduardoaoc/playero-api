<?php

namespace App\Support;

use App\Models\Event;

class EventPresenter
{
    public static function make(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'type' => $event->type,
            'date' => $event->date ? $event->date->format('Y-m-d') : null,
            'start_time' => $event->start_time,
            'end_time' => $event->end_time,
            'location' => $event->location,
            'max_people' => $event->max_people,
            'visibility' => $event->visibility,
            'is_paid' => (bool) $event->is_paid,
            'status' => $event->status,
            'description' => $event->description,
            'created_by' => $event->created_by,
        ];
    }
}
