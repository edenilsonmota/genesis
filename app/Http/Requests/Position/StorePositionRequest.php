<?php

namespace App\Http\Requests\Position;

use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;

class StorePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Position::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
            'grants_system_access' => ['sometimes', 'boolean'],
            'confirm_access_revocation' => ['sometimes', 'boolean'],
        ];
    }
}
