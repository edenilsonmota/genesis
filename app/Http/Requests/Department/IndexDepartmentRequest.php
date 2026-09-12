<?php

namespace App\Http\Requests\Department;

use App\Models\Department;
use App\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Department::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(Status::class)->only([Status::Active, Status::Inactive])],
        ];
    }
}
