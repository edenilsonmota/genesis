<?php

namespace App\Http\Requests\Area;

use App\Models\Area;
use App\Status;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAreaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Area::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in([Status::Active->value, Status::Inactive->value])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (Area::query()->exists()) {
                    $validator->errors()->add('name', 'Esta instalação já possui uma área cadastrada.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $description = Str::of($this->string('description')->toString())->trim()->toString();

        $this->merge([
            'name' => Str::squish($this->string('name')->toString()),
            'description' => $description ?: null,
        ]);
    }
}
