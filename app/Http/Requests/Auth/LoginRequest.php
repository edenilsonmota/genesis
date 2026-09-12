<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\PermissionService;
use App\Status;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/\A[a-z0-9._-]+\z/'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function authenticate(PermissionService $permissions): void
    {
        $this->ensureIsNotRateLimited();

        $authenticated = Auth::attempt([
            'username' => $this->string('username')->toString(),
            'password' => $this->string('password')->toString(),
            'status' => Status::Active->value,
        ], $this->boolean('remember'));

        if (! $authenticated) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => 'As credenciais informadas não são válidas.',
            ]);
        }

        $user = Auth::user();
        if ($user === null || ! $permissions->hasSystemAccess($user)) {
            Auth::guard('web')->logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => 'Seu acesso ao sistema não está disponível. Procure um administrador.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function throttleKey(): string
    {
        return Str::transliterate($this->string('username')->lower().'|'.$this->ip());
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => User::normalizeUsername($this->string('username')->toString()),
        ]);
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => "Muitas tentativas de acesso. Tente novamente em {$seconds} segundos.",
        ]);
    }
}
