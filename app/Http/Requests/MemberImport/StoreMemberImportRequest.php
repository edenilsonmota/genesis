<?php

namespace App\Http\Requests\MemberImport;

use App\Models\Church;
use App\Models\MemberImport;
use App\Status;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreMemberImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', MemberImport::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'church_id' => ['required', 'uuid', Rule::exists(Church::class, 'id')->where('status', Status::Active->value)],
            'file' => ['required', File::types(['xlsx'])->max('10mb'), 'extensions:xlsx'],
        ];
    }

    public function messages(): array
    {
        return [
            'church_id.required' => 'Selecione a igreja da importação.',
            'church_id.exists' => 'Selecione uma igreja ativa.',
            'file.required' => 'Selecione o arquivo XLSX.',
            'file.extensions' => 'Somente arquivos com extensão .xlsx são aceitos.',
        ];
    }
}
