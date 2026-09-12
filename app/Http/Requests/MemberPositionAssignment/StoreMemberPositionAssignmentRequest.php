<?php

namespace App\Http\Requests\MemberPositionAssignment;

use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemberPositionAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $member = $this->route('member');

        return $member instanceof Member && ($this->user()?->can('managePositions', $member) ?? false);
    }

    public function rules(): array
    {
        return [
            'member_church_membership_id' => ['required', 'uuid', 'exists:member_church_memberships,id'],
            'position_id' => ['required', 'uuid', 'exists:positions,id'],
            'started_at' => ['required', 'date'],
        ];
    }
}
