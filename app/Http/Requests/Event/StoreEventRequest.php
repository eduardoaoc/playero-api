<?php

namespace App\Http\Requests\Event;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3'],
            'type' => ['required', 'in:'.implode(',', Event::TYPES)],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'location' => ['required', 'string'],
            'max_people' => ['nullable', 'integer', 'min:1'],
            'visibility' => ['required', 'in:'.implode(',', Event::VISIBILITIES)],
            'is_paid' => ['required', 'boolean'],
            'status' => ['required', 'in:'.implode(',', [
                Event::STATUS_ACTIVE,
                Event::STATUS_INACTIVE,
            ])],
            'description' => ['nullable', 'string'],
        ];
    }
}
