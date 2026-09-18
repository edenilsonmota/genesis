<?php

namespace App\Http\Controllers;

use App\Http\Requests\Calendar\StoreCalendarEventTypeRequest;
use App\Services\CalendarEventTypeService;
use Illuminate\Http\JsonResponse;

class CalendarEventTypeController extends Controller
{
    public function store(StoreCalendarEventTypeRequest $request, CalendarEventTypeService $types): JsonResponse
    {
        $type = $types->createForCurrentScope($request->validated(), $request->user());

        return response()->json(['type' => ['id' => $type->id, 'name' => $type->name, 'color' => $type->color]], 201);
    }
}
