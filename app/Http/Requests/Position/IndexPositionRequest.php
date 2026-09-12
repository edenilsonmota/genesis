<?php

namespace App\Http\Requests\Position;

use App\Models\Position;
use App\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Position::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
            'status' => ['nullable', Rule::enum(Status::class)->only([Status::Active, Status::Inactive])],
            'grants_system_access' => ['nullable', Rule::in(['0', '1'])],
        ];
    }
}
