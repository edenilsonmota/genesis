@extends('layouts.guest')

@section('title', 'Entrar')

@section('content')
    <header class="mb-7">
        <p class="mb-2 text-sm font-semibold text-genesis-600">Bem-vindo</p>
        <h1 class="text-2xl font-semibold tracking-tight text-ink-950">Acesse sua conta</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500">Use seu nome de usuário e sua senha para continuar.</p>
    </header>

    <form class="grid gap-5" method="POST" action="{{ route('login.store') }}">
        @csrf

        <div>
            <label class="mb-2 block text-sm font-semibold text-slate-700" for="username">Nome de usuário</label>
            <input
                class="block w-full rounded-xl border bg-white px-3.5 py-3 text-sm text-ink-950 shadow-sm transition placeholder:text-slate-400 {{ $errors->has('username') ? 'border-red-400' : 'border-stone-300 hover:border-stone-400' }}"
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
                <p class="mt-2 text-sm text-red-600" id="username-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-2 block text-sm font-semibold text-slate-700" for="password">Senha</label>
            <input
                class="block w-full rounded-xl border bg-white px-3.5 py-3 text-sm text-ink-950 shadow-sm transition {{ $errors->has('password') ? 'border-red-400' : 'border-stone-300 hover:border-stone-400' }}"
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
                @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif
            >
            @error('password')
                <p class="mt-2 text-sm text-red-600" id="password-error">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex w-fit items-center gap-2.5 text-sm text-slate-600">
            <input class="size-4 rounded border-stone-300 text-genesis-600" name="remember" type="checkbox" value="1" @checked(old('remember'))>
            Lembrar de mim
        </label>

        <button class="rounded-xl bg-genesis-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-genesis-700" type="submit">
            Entrar
        </button>
    </form>
@endsection
