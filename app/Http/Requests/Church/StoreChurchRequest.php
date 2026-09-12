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
            'postal_code' => ['required', 'digits:8'],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'street' => ['required', 'string', 'max:255'],
            'neighborhood' => ['required', 'string', 'max:255'],
            'number' => ['required', 'string', 'max:30'],
            'complement' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in([Status::Active->value, Status::Inactive->value])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $validator->errors()->hasAny(['state_id', 'city_id'])) {
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
            'postal_code' => preg_replace('/\D/', '', $this->string('postal_code')->toString()),
            'street' => Str::squish($this->string('street')->toString()),
            'neighborhood' => Str::squish($this->string('neighborhood')->toString()),
            'number' => Str::squish($this->string('number')->toString()),
            'complement' => $complement ?: null,
        ]);
    }
}
