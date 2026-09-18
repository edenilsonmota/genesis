<?php

namespace App\Http\Requests\Audit;

use App\PermissionLevel;
use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

class IndexAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && app(PermissionService::class)->can($user, 'audit', PermissionLevel::Read);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:150'],
            'action' => ['nullable', 'string', 'max:80'],
            'resource' => ['nullable', 'string', 'max:80'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->string('search')->trim()->toString() ?: null,
            'action' => $this->string('action')->trim()->toString() ?: null,
            'resource' => $this->string('resource')->trim()->toString() ?: null,
        ]);
    }
}
