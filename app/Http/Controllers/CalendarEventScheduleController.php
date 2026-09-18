<?php

namespace App\Http\Controllers;

use App\Http\Requests\Calendar\RescheduleCalendarEventRequest;
use App\Models\CalendarEvent;
use App\Services\CalendarEventService;
use Illuminate\Http\JsonResponse;

class CalendarEventScheduleController extends Controller
{
    public function __invoke(
        RescheduleCalendarEventRequest $request,
        CalendarEvent $calendarEvent,
        CalendarEventService $events,
    ): JsonResponse {
        $events->reschedule(
            $calendarEvent,
            $request->validated('start'),
            $request->validated('end'),
            $request->boolean('all_day'),
        );

        return response()->json(['message' => 'Data e horário atualizados.']);
    }
}
