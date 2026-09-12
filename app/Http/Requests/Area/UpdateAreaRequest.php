<?php

namespace App\Http\Requests\Area;

use App\Models\Area;
use App\Status;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateAreaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $area = $this->route('area');

        return $area instanceof Area && ($this->user()?->can('update', $area) ?? false);
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

    protected function prepareForValidation(): void
    {
        $description = Str::of($this->string('description')->toString())->trim()->toString();

        $this->merge([
            'name' => Str::squish($this->string('name')->toString()),
            'description' => $description ?: null,
        ]);
    }
}
