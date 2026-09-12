@extends('layouts.guest')

@section('title', 'Entrar')

@section('content')
    <header class="mb-7">
        <p class="mb-2 text-sm font-semibold text-brand-primary">Bem-vindo</p>
        <h1 class="text-2xl font-semibold tracking-tight text-text-primary">Acesse sua conta</h1>
        <p class="mt-2 text-sm leading-6 text-text-secondary">Use seu nome de usuário e sua senha para continuar.</p>
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
            <input
                class="ui-input px-3.5 py-3 {{ $errors->has('password') ? 'border-danger' : '' }}"
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
                @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif
            >
            @error('password')
                <p class="mt-2 text-sm text-danger" id="password-error">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex w-fit items-center gap-2.5 text-sm text-text-secondary">
            <input class="size-4 rounded border-border-default accent-brand-primary" name="remember" type="checkbox" value="1" @checked(old('remember'))>
            Lembrar de mim
        </label>

        <button class="ui-button-primary py-3" type="submit">
            Entrar
        </button>
    </form>
@endsection
