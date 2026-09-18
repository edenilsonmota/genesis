<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\CalendarEventType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CalendarEventType> */
class CalendarEventTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'area_id' => Area::factory(),
            'name' => fake()->unique()->words(2, true),
            'color' => '#0051F5',
        ];
    }
}
