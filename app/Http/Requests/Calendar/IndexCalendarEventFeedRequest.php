<?php

namespace App\Http\Requests\Calendar;

use App\Enums\CalendarEventStatus;
use App\Models\CalendarEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCalendarEventFeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CalendarEvent::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'type' => ['nullable', 'uuid', 'exists:calendar_event_types,id'],
            'status' => ['nullable', Rule::enum(CalendarEventStatus::class)],
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
        ];
    }
}
