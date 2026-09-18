@extends('layouts.guest')

@section('title', 'Entrar')
@section('splitGuestLayout', 'true')

@section('content')
    <header class="mb-8">
        <p class="mb-2 text-sm font-semibold text-brand-primary">Bem-vindo de volta</p>
        <h1 class="text-3xl font-semibold tracking-tight text-text-primary">Acesse sua conta</h1>
        <p class="mt-3 text-sm leading-6 text-text-secondary">Entre com seu nome de usuário e sua senha para acessar o Genesis+.</p>
    </header>

    <form class="grid gap-5" method="POST" action="{{ route('login.store') }}">
        @csrf

        <div>
            <label class="ui-label mb-2" for="username">Nome de usuário</label>
            <input
                class="ui-input px-3.5 py-3 {{ $errors->has('username') ? 'border-danger' : '' }}"
                id="username"
                name="username"
                type="text"
                value="{{ old('username') }}"
                autocomplete="username"
                autocapitalize="none"
                spellcheck="false"
                required
                autofocus
                @if ($errors->has('username')) aria-invalid="true" aria-describedby="username-error" @endif
            >
            @error('username')
                <p class="mt-2 text-sm text-danger" id="username-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="ui-label mb-2" for="password">Senha</label>
            <div class="relative">
                <input
                    class="ui-input py-3 pl-3.5 pr-12 {{ $errors->has('password') ? 'border-danger' : '' }}"
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif
                >
                <button class="absolute inset-y-0 right-0 grid w-12 place-items-center rounded-r-xl text-text-secondary transition hover:text-brand-primary focus-visible:outline-2 focus-visible:outline-offset-[-3px] focus-visible:outline-brand-cyan" type="button" aria-label="Mostrar senha" aria-pressed="false" data-password-toggle data-password-target="password">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" data-password-show-icon><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                    <svg class="hidden size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" data-password-hide-icon><path d="m4 4 16 16M9.7 6.3A10.5 10.5 0 0 1 12 6c6 0 9.5 6 9.5 6a17 17 0 0 1-2.1 2.8M6.1 7.7C3.8 9.5 2.5 12 2.5 12s3.5 6 9.5 6a9.7 9.7 0 0 0 3-.5M10.3 10.3a2.5 2.5 0 0 0 3.4 3.4"/></svg>
                </button>
            </div>
            @error('password')
                <p class="mt-2 text-sm text-danger" id="password-error">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex w-fit items-center gap-2.5 text-sm text-text-secondary">
            <input class="size-4 rounded border-border-default accent-brand-primary" name="remember" type="checkbox" value="1" @checked(old('remember'))>
            Lembrar de mim
        </label>

        <button class="ui-button-primary mt-1 py-3" type="submit">
            Entrar
        </button>
    </form>
@endsection
