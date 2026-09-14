<?php

namespace App\Http\Requests\Church;

use App\Models\Area;
use App\Models\Church;
use App\Models\City;
use App\Status;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreChurchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Church::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'area_id' => ['prohibited'],
            'name' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'digits:8'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'street' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:30'],
            'complement' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in([Status::Active->value, Status::Inactive->value])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $hasState = filled($this->input('state_id'));
                $hasCity = filled($this->input('city_id'));
                if ($hasState xor $hasCity) {
                    $validator->errors()->add($hasState ? 'city_id' : 'state_id', 'Estado e cidade devem ser informados juntos.');
                }

                if ($hasState && $hasCity && ! $validator->errors()->hasAny(['state_id', 'city_id'])) {
                    $cityBelongsToState = City::query()
                        ->whereKey($this->integer('city_id'))
                        ->where('state_id', $this->integer('state_id'))
                        ->exists();

                    if (! $cityBelongsToState) {
                        $validator->errors()->add('city_id', 'A cidade selecionada não pertence ao estado informado.');
                    }
                }

                $area = Area::query()->first();

                if ($area === null) {
                    $validator->errors()->add('area', 'Cadastre a área antes de adicionar uma igreja.');

                    return;
                }

                if (! $validator->errors()->has('name') && Church::query()
                    ->whereBelongsTo($area)
                    ->whereRaw('LOWER(name) = LOWER(?)', [$this->string('name')->toString()])
                    ->exists()) {
                    $validator->errors()->add('name', 'Já existe uma igreja com este nome na área.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $complement = $this->string('complement')->trim()->toString();

        $this->merge([
            'name' => Str::squish($this->string('name')->toString()),
            'postal_code' => filled($this->input('postal_code')) ? preg_replace('/\D/', '', $this->string('postal_code')->toString()) : null,
            'state_id' => filled($this->input('state_id')) ? $this->input('state_id') : null,
            'city_id' => filled($this->input('city_id')) ? $this->input('city_id') : null,
            'street' => filled($this->input('street')) ? Str::squish($this->string('street')->toString()) : null,
            'neighborhood' => filled($this->input('neighborhood')) ? Str::squish($this->string('neighborhood')->toString()) : null,
            'number' => filled($this->input('number')) ? Str::squish($this->string('number')->toString()) : null,
            'complement' => $complement ?: null,
            'status' => $this->input('status', Status::Active->value),
        ]);
    }
}
