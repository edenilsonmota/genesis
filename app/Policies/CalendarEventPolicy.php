<?php

namespace App\Policies;

use App\Enums\CalendarEventStatus;
use App\Enums\CalendarEventVisibility;
use App\Models\CalendarEvent;
use App\Models\User;
use App\PermissionLevel;
use App\Services\PermissionService;

class CalendarEventPolicy
{
    public function __construct(private PermissionService $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->can($user, 'calendar', PermissionLevel::Read);
    }

    public function view(User $user, CalendarEvent $calendarEvent): bool
    {
        if ($user->isGlobalAdministrator()) {
            return true;
        }

        $church = $this->permissions->currentChurch($user);
        if ($church === null
            || $calendarEvent->area_id !== $church->area_id
            || ($calendarEvent->church_id !== null && $calendarEvent->church_id !== $church->id)
            || ! $this->permissions->can($user, 'calendar', PermissionLevel::Read, $church)) {
            return false;
        }

        $isOwner = $calendarEvent->created_by_user_id === $user->id;
        $isResponsible = $user->member_id !== null && $calendarEvent->responsible_member_id === $user->member_id;
        $canManage = $this->permissions->can($user, 'calendar', PermissionLevel::Write, $church);

        if ($calendarEvent->status === CalendarEventStatus::Draft && ! $isOwner && ! $isResponsible && ! $canManage) {
            return false;
        }

        return match ($calendarEvent->visibility) {
            CalendarEventVisibility::Church => true,
            CalendarEventVisibility::Private => $isOwner || $isResponsible || $canManage,
            CalendarEventVisibility::Department => $canManage
                || ($calendarEvent->department_id !== null
                    && $this->permissions->hasActiveDepartmentAssignment($user, $church, $calendarEvent->department_id)),
        };
    }

    public function create(User $user): bool
    {
        return $this->permissions->can($user, 'calendar', PermissionLevel::Write);
    }

    public function update(User $user, CalendarEvent $calendarEvent): bool
    {
        if ($calendarEvent->isCancelled()) {
            return false;
        }

        if ($user->isGlobalAdministrator()) {
            return true;
        }

        $church = $this->permissions->currentChurch($user);
        if ($church === null || $calendarEvent->church_id === null || ! $this->view($user, $calendarEvent)) {
            return false;
        }

        return $this->permissions->can($user, 'calendar', PermissionLevel::Write, $church)
            || ($calendarEvent->creator_can_edit && $calendarEvent->created_by_user_id === $user->id)
            || ($calendarEvent->responsible_can_edit && $user->member_id !== null && $calendarEvent->responsible_member_id === $user->member_id);
    }

    public function cancel(User $user, CalendarEvent $calendarEvent): bool
    {
        return $this->update($user, $calendarEvent);
    }
}
