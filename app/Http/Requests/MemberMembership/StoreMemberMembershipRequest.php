<?php

namespace App\Http\Requests\MemberMembership;

use App\Models\Church;
use App\Models\Member;
use App\Status;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberMembershipRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $member = $this->route('member');

        return $member instanceof Member
            && ($this->user()?->can('manageMemberships', $member) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'church_id' => [
                'required',
                'uuid',
                Rule::exists(Church::class, 'id')->where('status', Status::Active->value),
            ],
            'joined_at' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'church_id.exists' => 'Selecione uma igreja ativa.',
            'joined_at.before_or_equal' => 'A data de entrada não pode estar no futuro.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'church_id' => $this->string('church_id')->trim()->toString() ?: null,
            'joined_at' => $this->string('joined_at')->trim()->toString() ?: null,
        ]);
    }
}
