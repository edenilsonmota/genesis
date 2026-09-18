<?php

namespace App\Http\Requests\Calendar;

class UpdateCalendarEventRequest extends SaveCalendarEventRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('calendarEvent')) ?? false;
    }
}
