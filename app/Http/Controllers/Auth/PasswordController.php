<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordUpdateRequest;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.change-password');
    }

    public function update(PasswordUpdateRequest $request, AuditService $audit): RedirectResponse
    {
        $request->user()->forceFill([
            'password' => Hash::make($request->string('password')->toString()),
            'must_change_password' => false,
        ])->save();
        $audit->record('user.password_changed', 'users', $request->user(), details: ['must_change_password' => false]);

        $request->session()->regenerate();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Senha alterada com sucesso.');
    }
}
