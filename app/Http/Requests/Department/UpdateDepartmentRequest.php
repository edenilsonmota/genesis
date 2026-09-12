<?php

namespace App\Http\Requests\Department;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department && ($this->user()?->can('update', $department) ?? false);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
