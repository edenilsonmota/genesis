<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\PasswordUpdateRequest;
use App\Http\Requests\Profile\UpdateProfileDetailsRequest;
use App\Http\Requests\Profile\UpdateProfileUsernameRequest;
use App\Services\AuditService;
use App\Services\ProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        $user = request()->user()->load('member');

        return view('profile.edit', compact('user'));
    }

    public function updateDetails(UpdateProfileDetailsRequest $request, ProfileService $profiles, AuditService $audit): RedirectResponse
    {
        $user = $profiles->updateDetails($request->user(), $request->safe()->except(['profile_photo']), $request->file('profile_photo'));
        $audit->record('user.profile_updated', 'users', $user, details: ['member_profile' => $user->member_id !== null]);

        return redirect()->route('profile.edit')->with('success', 'Informações pessoais atualizadas.');
    }

    public function updateUsername(UpdateProfileUsernameRequest $request, ProfileService $profiles, AuditService $audit): RedirectResponse
    {
        $user = $profiles->updateUsername($request->user(), $request->validated('username'));
        $audit->record('user.username_updated', 'users', $user);

        return redirect()->route('profile.edit')->with('success', 'Nome de usuário atualizado.');
    }

    public function updatePassword(PasswordUpdateRequest $request, ProfileService $profiles, AuditService $audit): RedirectResponse
    {
        $user = $profiles->updatePassword($request->user(), $request->string('password')->toString());
        $audit->record('user.password_changed', 'users', $user, details: ['must_change_password' => false]);
        $request->session()->regenerate();

        return redirect()->route('profile.edit')->with('success', 'Senha atualizada com sucesso.');
    }
}
