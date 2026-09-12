@extends('layouts.guest')

@section('title', 'Trocar senha')

@section('content')
    <header class="mb-7">
        <p class="mb-2 text-sm font-semibold text-warning">Ação necessária</p>
        <h1 class="text-2xl font-semibold tracking-tight text-text-primary">Crie uma nova senha</h1>
        <p class="mt-2 text-sm leading-6 text-text-secondary">Sua senha atual é temporária. Escolha uma senha com ao menos 12 caracteres, letras maiúsculas, minúsculas e números.</p>
    </header>

    <form class="grid gap-5" method="POST" action="{{ route('password.change.update') }}">
        @csrf
        @method('PUT')

        <div>
            <label class="ui-label mb-2" for="current_password">Senha atual</label>
            <input class="ui-input px-3.5 py-3 {{ $errors->has('current_password') ? 'border-danger' : '' }}" id="current_password" name="current_password" type="password" autocomplete="current-password" required @if ($errors->has('current_password')) aria-invalid="true" aria-describedby="current-password-error" @endif>
            @error('current_password')
                <p class="mt-2 text-sm text-danger" id="current-password-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="ui-label mb-2" for="password">Nova senha</label>
            <input class="ui-input px-3.5 py-3 {{ $errors->has('password') ? 'border-danger' : '' }}" id="password" name="password" type="password" autocomplete="new-password" required @if ($errors->has('password')) aria-invalid="true" aria-describedby="new-password-error" @endif>
            @error('password')
                <p class="mt-2 text-sm text-danger" id="new-password-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="ui-label mb-2" for="password_confirmation">Confirme a nova senha</label>
            <input class="ui-input px-3.5 py-3" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        </div>

        <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
            <button class="ui-button-primary py-3" type="submit">Salvar nova senha</button>
            <button class="ui-button-outline w-full py-3" type="submit" form="logout-form">Sair</button>
        </div>
    </form>

    <form id="logout-form" class="hidden" method="POST" action="{{ route('logout') }}">
        @csrf
    </form>
@endsection
