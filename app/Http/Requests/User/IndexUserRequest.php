<?php

namespace App\Http\Requests\User;

use App\Models\User;
use App\Status;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in([Status::Active->value, Status::Inactive->value])], 'church_id' => ['nullable', 'uuid', 'exists:churches,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['search' => $this->string('search')->trim()->toString() ?: null, 'status' => $this->string('status')->trim()->toString() ?: null, 'church_id' => $this->string('church_id')->trim()->toString() ?: null]);
    }
}
