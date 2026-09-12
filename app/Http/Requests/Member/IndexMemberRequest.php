<?php

namespace App\Http\Requests\Member;

use App\Enums\Sex;
use App\Models\Member;
use App\Status;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Member::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'church_id' => ['nullable', 'uuid', 'exists:churches,id'],
            'status' => ['nullable', Rule::in([Status::Active->value, Status::Inactive->value])],
            'sex' => ['nullable', Rule::enum(Sex::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->string('search')->trim()->toString() ?: null,
            'church_id' => $this->string('church_id')->trim()->toString() ?: null,
            'status' => $this->string('status')->trim()->toString() ?: null,
            'sex' => $this->string('sex')->trim()->toString() ?: null,
        ]);
    }
}
