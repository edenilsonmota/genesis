<?php

namespace App\Http\Requests\Finance\FinancialAccount;

use App\Enums\FinancialAccountType;
use App\Models\FinancialAccount;
use App\Status;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFinancialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FinancialAccount::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'owner_type' => ['required', Rule::in(['area', 'church'])],
            'area_id' => [
                'nullable',
                'required_if:owner_type,area',
                'prohibited_unless:owner_type,area',
                'uuid',
                Rule::exists('areas', 'id')->where(fn (Builder $query): Builder => $query->where('status', Status::Active->value)),
            ],
            'church_id' => [
                'nullable',
                'required_if:owner_type,church',
                'prohibited_unless:owner_type,church',
                'uuid',
                Rule::exists('churches', 'id')->where(fn (Builder $query): Builder => $query->where('status', Status::Active->value)),
            ],
            'type' => ['required', Rule::enum(FinancialAccountType::class)],
            'institution' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::enum(Status::class)->only([Status::Active, Status::Inactive])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['name', 'owner_type', 'area_id', 'church_id'])) {
                return;
            }

            $query = FinancialAccount::query()
                ->whereRaw('LOWER(name) = ?', [Str::lower($this->string('name')->toString())]);

            if ($this->string('owner_type')->toString() === 'area') {
                $query->forArea($this->string('area_id')->toString());
            } else {
                $query->forChurch($this->string('church_id')->toString());
            }

            $financialAccount = $this->route('financialAccount');
            if ($financialAccount instanceof FinancialAccount) {
                $query->whereKeyNot($financialAccount->id);
            }

            if ($query->exists()) {
                $validator->errors()->add('name', 'Já existe uma conta financeira com este nome para o proprietário selecionado.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
            'institution' => filled($this->input('institution')) ? Str::squish((string) $this->input('institution')) : null,
            'description' => filled($this->input('description')) ? trim((string) $this->input('description')) : null,
            'area_id' => filled($this->input('area_id')) ? $this->input('area_id') : null,
            'church_id' => filled($this->input('church_id')) ? $this->input('church_id') : null,
        ]);
    }
}
