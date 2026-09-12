<?php

namespace App\Http\Requests\Position;

use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePositionPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $position = $this->route('position');

        return $position instanceof Position && ($this->user()?->can('updatePermissions', $position) ?? false);
    }

    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array'],
            'permissions.*' => ['required', Rule::in(['none', 'read', 'write'])],
            'confirm_access_revocation' => ['sometimes', 'boolean'],
        ];
    }
}
