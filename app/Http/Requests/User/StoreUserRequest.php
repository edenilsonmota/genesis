<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'uuid', 'exists:members,id', Rule::unique(User::class, 'member_id')],
            'username' => [
                'required', 'string', 'min:3', 'max:50',
                'regex:/\A[a-z0-9._-]+\z/',
                Rule::notIn(['root', 'support', 'system']),
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('username') && User::query()
                ->whereRaw('LOWER(username) = ?', [$this->string('username')->toString()])
                ->exists()) {
                $validator->errors()->add('username', 'Este nome de usuário já está em uso.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => User::normalizeUsername($this->string('username')->toString()),
            'member_id' => $this->string('member_id')->trim()->toString() ?: null,
        ]);
    }
}
