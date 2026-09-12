<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Painel') · {{ config('app.name', 'Genesis') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="min-h-screen lg:grid lg:grid-cols-[17rem_1fr]">
        <aside class="border-b border-stone-200 bg-white lg:min-h-screen lg:border-r lg:border-b-0">
            <div class="flex h-20 items-center justify-between px-5 lg:px-6">
                <x-application-logo />
                <button
                    class="rounded-xl border border-stone-200 p-2 text-slate-600 lg:hidden"
                    type="button"
                    aria-label="Alternar menu"
                    aria-controls="main-navigation"
                    aria-expanded="false"
                    data-navigation-toggle
                >
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M4 7h16M4 12h16M4 17h16" />
                    </svg>
                </button>
            </div>

            <nav id="main-navigation" class="hidden px-4 pb-5 lg:block" data-navigation-panel aria-label="Navegação principal">
                <p class="px-3 pb-2 text-xs font-semibold tracking-widest text-slate-400 uppercase">Principal</p>
                <a @class([
                    'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold',
                    'bg-genesis-50 text-genesis-700' => request()->routeIs('dashboard'),
                    'text-slate-600 hover:bg-stone-50 hover:text-ink-950' => ! request()->routeIs('dashboard'),
                ]) href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>
                    <span @class(['size-2 rounded-full', 'bg-genesis-500' => request()->routeIs('dashboard'), 'bg-stone-300' => ! request()->routeIs('dashboard')]) aria-hidden="true"></span>
                    Visão geral
                </a>

                @can('viewAny', App\Models\Area::class)
                    <a @class([
                        'mt-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold',
                        'bg-genesis-50 text-genesis-700' => request()->routeIs('organization.*'),
                        'text-slate-600 hover:bg-stone-50 hover:text-ink-950' => ! request()->routeIs('organization.*'),
                    ]) href="{{ route('organization.index') }}" @if (request()->routeIs('organization.*')) aria-current="page" @endif>
                        <span @class(['size-2 rounded-full', 'bg-genesis-500' => request()->routeIs('organization.*'), 'bg-stone-300' => ! request()->routeIs('organization.*')]) aria-hidden="true"></span>
                        Área e Igrejas
                    </a>
                @endcan

                <p class="px-3 pt-7 pb-2 text-xs font-semibold tracking-widest text-slate-400 uppercase">Em breve</p>
                <div class="grid gap-1 text-sm text-slate-400" aria-label="Módulos futuros">
                    <span class="rounded-xl px-3 py-2.5">Membros</span>
                    <span class="rounded-xl px-3 py-2.5">Usuários</span>
                    <span class="rounded-xl px-3 py-2.5">Grupos de acesso</span>
                </div>
            </nav>
        </aside>

        <div class="min-w-0">
            <header class="border-b border-stone-200 bg-white/90 backdrop-blur">
                <div class="flex min-h-20 items-center justify-between gap-4 px-5 py-3 sm:px-8">
                    <div>
                        <p class="text-xs font-medium tracking-wider text-slate-500 uppercase">Genesis</p>
                        <p class="font-semibold text-ink-950">@yield('header', 'Painel')</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="hidden text-right sm:block">
                            <p class="text-sm font-semibold text-ink-950">{{ auth()->user()->display_name }}</p>
                            <p class="text-xs text-slate-500">{{ '@'.auth()->user()->username }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-stone-300 hover:bg-stone-50" type="submit">
                                Sair
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="px-5 py-7 sm:px-8 sm:py-10">
                @if (session('success'))
                    <div class="mb-6">
                        <x-flash-message :message="session('success')" />
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
