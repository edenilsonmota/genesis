<?php

namespace App\Http\Requests\Member;

use App\Enums\Sex;
use App\Models\City;
use App\Models\Member;
use App\Rules\ValidCpf;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $member = $this->route('member');

        return $member instanceof Member && ($this->user()?->can('update', $member) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $member = $this->route('member');

        return [
            'name' => ['required', 'string', 'max:255'],
            'cpf' => ['required', 'digits:11', new ValidCpf, Rule::unique(Member::class, 'cpf')->ignore($member)],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'digits_between:10,11'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'sex' => ['nullable', Rule::enum(Sex::class)],
            'postal_code' => ['nullable', 'digits:8'],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'street' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:30'],
            'complement' => ['nullable', 'string', 'max:255'],
            'status' => ['prohibited'],
            'church_id' => ['prohibited'],
            'joined_at' => ['prohibited'],
            'is_primary' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'birth_date.before_or_equal' => 'A data de nascimento não pode estar no futuro.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $validator->errors()->hasAny(['state_id', 'city_id']) && ! City::query()
                    ->whereKey($this->integer('city_id'))
                    ->where('state_id', $this->integer('state_id'))
                    ->exists()) {
                    $validator->errors()->add('city_id', 'A cidade selecionada não pertence ao estado informado.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $nullableString = fn (string $key): ?string => ($value = Str::squish($this->string($key)->toString())) !== ''
            ? $value
            : null;

        $this->merge([
            'name' => Str::squish($this->string('name')->toString()),
            'cpf' => preg_replace('/\D/', '', $this->string('cpf')->toString()),
            'email' => ($email = $this->string('email')->trim()->lower()->toString()) !== '' ? $email : null,
            'phone' => ($phone = preg_replace('/\D/', '', $this->string('phone')->toString()) ?? '') !== '' ? $phone : null,
            'birth_date' => $this->string('birth_date')->trim()->toString() ?: null,
            'sex' => $this->string('sex')->trim()->toString() ?: null,
            'postal_code' => ($postalCode = preg_replace('/\D/', '', $this->string('postal_code')->toString()) ?? '') !== '' ? $postalCode : null,
            'state_id' => $this->input('state_id') ?: null,
            'city_id' => $this->input('city_id') ?: null,
            'street' => $nullableString('street'),
            'neighborhood' => $nullableString('neighborhood'),
            'number' => $nullableString('number'),
            'complement' => $nullableString('complement'),
        ]);
    }
}
