<?php

namespace App\Http\Requests\MemberImport;

use App\Models\Church;
use App\Models\MemberImport;
use App\Status;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SelectMemberImportChurchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', MemberImport::class) ?? false;
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
        ];
    }
}
