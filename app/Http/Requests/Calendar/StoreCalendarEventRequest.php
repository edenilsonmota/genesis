<?php

namespace App\Http\Requests\Calendar;

use App\Models\CalendarEvent;

class StoreCalendarEventRequest extends SaveCalendarEventRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CalendarEvent::class) ?? false;
    }
}
