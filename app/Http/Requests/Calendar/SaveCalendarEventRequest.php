<?php

namespace App\Http\Requests\Calendar;

use App\Enums\CalendarEventStatus;
use App\Enums\CalendarEventVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class SaveCalendarEventRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'calendar_event_type_id' => ['required', 'uuid', 'exists:calendar_event_types,id'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', Rule::requiredIf(fn (): bool => ! $this->boolean('all_day')), 'date_format:H:i'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'end_time' => ['nullable', Rule::requiredIf(fn (): bool => ! $this->boolean('all_day')), 'date_format:H:i'],
            'all_day' => ['required', 'boolean'],
            'scope_type' => ['required', Rule::in(['area', 'church'])],
            'church_id' => ['nullable', 'required_if:scope_type,church', 'uuid', 'exists:churches,id'],
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
            'responsible_member_id' => ['nullable', 'uuid', 'exists:members,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'visibility' => ['required', Rule::enum(CalendarEventVisibility::class)],
            'status' => ['required', Rule::enum(CalendarEventStatus::class)],
            'creator_can_edit' => ['required', 'boolean'],
            'responsible_can_edit' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('visibility') === CalendarEventVisibility::Department->value && ! $this->filled('department_id')) {
                $validator->errors()->add('department_id', 'Selecione um departamento para esta visibilidade.');
            }

            if ($this->input('status') === CalendarEventStatus::Cancelled->value) {
                $validator->errors()->add('status', 'Use a ação de cancelamento para cancelar um evento.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'all_day' => $this->boolean('all_day'),
            'creator_can_edit' => $this->boolean('creator_can_edit'),
            'responsible_can_edit' => $this->boolean('responsible_can_edit'),
        ]);
    }
}
