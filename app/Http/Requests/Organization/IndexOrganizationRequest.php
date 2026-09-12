<?php

namespace App\Http\Requests\Organization;

use App\Models\Area;
use App\Status;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexOrganizationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Area::class) ?? false;
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
            'status' => ['nullable', Rule::in([Status::Active->value, Status::Inactive->value])],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'panel' => ['nullable', Rule::in(['create-area', 'edit-area', 'create-church', 'view-church', 'edit-church'])],
            'church' => ['nullable', 'uuid', 'exists:churches,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->string('search')->trim()->toString() ?: null,
            'status' => $this->string('status')->trim()->toString() ?: null,
            'state_id' => $this->input('state_id') ?: null,
            'city_id' => $this->input('city_id') ?: null,
            'panel' => $this->string('panel')->trim()->toString() ?: null,
            'church' => $this->string('church')->trim()->toString() ?: null,
        ]);
    }
}
