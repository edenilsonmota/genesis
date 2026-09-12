@extends('layouts.guest')

@section('title', 'Trocar senha')

@section('content')
    <header class="mb-7">
        <p class="mb-2 text-sm font-semibold text-amber-700">Ação necessária</p>
        <h1 class="text-2xl font-semibold tracking-tight text-ink-950">Crie uma nova senha</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500">Sua senha atual é temporária. Escolha uma senha com ao menos 12 caracteres, letras maiúsculas, minúsculas e números.</p>
    </header>

    <form class="grid gap-5" method="POST" action="{{ route('password.change.update') }}">
        @csrf
        @method('PUT')

        <div>
            <label class="mb-2 block text-sm font-semibold text-slate-700" for="current_password">Senha atual</label>
            <input class="block w-full rounded-xl border border-stone-300 bg-white px-3.5 py-3 text-sm shadow-sm" id="current_password" name="current_password" type="password" autocomplete="current-password" required @if ($errors->has('current_password')) aria-invalid="true" aria-describedby="current-password-error" @endif>
            @error('current_password')
                <p class="mt-2 text-sm text-red-600" id="current-password-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-2 block text-sm font-semibold text-slate-700" for="password">Nova senha</label>
            <input class="block w-full rounded-xl border border-stone-300 bg-white px-3.5 py-3 text-sm shadow-sm" id="password" name="password" type="password" autocomplete="new-password" required @if ($errors->has('password')) aria-invalid="true" aria-describedby="new-password-error" @endif>
            @error('password')
                <p class="mt-2 text-sm text-red-600" id="new-password-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-2 block text-sm font-semibold text-slate-700" for="password_confirmation">Confirme a nova senha</label>
            <input class="block w-full rounded-xl border border-stone-300 bg-white px-3.5 py-3 text-sm shadow-sm" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        </div>

        <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
            <button class="rounded-xl bg-genesis-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-genesis-700" type="submit">Salvar nova senha</button>
            <button class="w-full rounded-xl border border-stone-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-stone-50" type="submit" form="logout-form">Sair</button>
        </div>
    </form>

    <form id="logout-form" class="hidden" method="POST" action="{{ route('logout') }}">
        @csrf
    </form>
@endsection
