<?php

namespace App\Http\Requests\Profile;

use App\Models\Member;
use App\Rules\ValidCpf;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProfileDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $member = $this->user()?->member;

        return [
            'name' => ['required', 'string', 'max:255'],
            'cpf' => [
                $member === null ? 'nullable' : 'required',
                'digits:11',
                new ValidCpf,
                Rule::unique(Member::class, 'cpf')->ignore($member),
            ],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'digits_between:10,11'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_profile_photo' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::squish($this->string('name')->toString()),
            'cpf' => ($cpf = preg_replace('/\D/', '', $this->string('cpf')->toString()) ?? '') !== '' ? $cpf : null,
            'email' => ($email = $this->string('email')->trim()->lower()->toString()) !== '' ? $email : null,
            'phone' => ($phone = preg_replace('/\D/', '', $this->string('phone')->toString()) ?? '') !== '' ? $phone : null,
        ]);
    }
}
