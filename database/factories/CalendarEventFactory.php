<?php

namespace Database\Factories;

use App\Enums\CalendarEventStatus;
use App\Enums\CalendarEventVisibility;
use App\Models\Area;
use App\Models\CalendarEvent;
use App\Models\CalendarEventType;
use App\Models\Church;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CalendarEvent> */
class CalendarEventFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = now()->addDays(fake()->numberBetween(1, 60))->setTime(19, 0);

        return [
            'area_id' => Area::factory(),
            'church_id' => null,
            'department_id' => null,
            'responsible_member_id' => null,
            'created_by_user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'calendar_event_type_id' => fn (array $attributes): string => CalendarEventType::factory()
                ->create(['area_id' => $attributes['area_id']])->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'all_day' => false,
            'location' => fake()->optional()->streetName(),
            'description' => fake()->optional()->sentence(),
            'visibility' => CalendarEventVisibility::Church,
            'status' => CalendarEventStatus::Confirmed,
            'creator_can_edit' => true,
            'responsible_can_edit' => true,
        ];
    }

    public function forChurch(?Church $church = null): static
    {
        return $this->state(function () use ($church): array {
            $church ??= Church::factory()->create();

            return ['area_id' => $church->area_id, 'church_id' => $church->id];
        });
    }
}
