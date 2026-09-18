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
    <div class="min-h-screen lg:grid" data-sidebar-shell data-sidebar-expanded="false" data-sidebar-ready="false">
        <aside class="hidden min-h-screen border-r border-border-default bg-surface-card lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col" data-desktop-sidebar data-sidebar-hover-expand data-expanded="false">
            <div class="flex h-16 items-center border-b border-border-default px-4" data-sidebar-brand>
                <x-application-logo class="min-w-0 overflow-hidden" />
            </div>

            <nav id="main-navigation" class="flex-1 overflow-y-auto px-2.5 py-4" data-navigation-panel aria-label="Navegação principal">
                <x-navigation.sidebar-links context="desktop" />
            </nav>
        </aside>

        <div class="min-w-0">
            <header class="border-b border-border-default bg-surface-card/90 backdrop-blur">
                <div class="flex min-h-16 items-center justify-between gap-4 px-5 py-2 sm:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <button class="grid size-10 shrink-0 place-items-center rounded-xl bg-surface-muted text-brand-primary lg:hidden" type="button" aria-label="Abrir navegação" aria-controls="mobile-sidebar" aria-expanded="false" data-navigation-toggle data-mobile-sidebar-open>
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
                        </button>
                        <div class="min-w-0">
                            <p class="text-xs font-medium tracking-wider text-text-secondary uppercase">Genesis+</p>
                            <p class="truncate font-semibold text-text-primary">@yield('header', 'Painel')</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2" data-header-user>
                        @if (($availableAccessChurches ?? collect())->isNotEmpty())
                            <form class="hidden md:block" method="POST" action="{{ route('active-church.update') }}">
                                @csrf
                                @method('PATCH')
                                <label class="sr-only" for="active_church_id">Igreja ativa</label>
                                <select class="ui-select w-auto max-w-[42vw] py-2 text-xs" id="active_church_id" name="church_id" onchange="this.form.submit()" aria-label="Igreja ativa" data-autosize-select data-select-min-width="7" data-select-max-width="28">
                                    @foreach ($availableAccessChurches as $accessChurch)
                                        <option value="{{ $accessChurch->id }}" @selected(($activeChurch?->id ?? null) === $accessChurch->id)>{{ $accessChurch->name }}</option>
                                    @endforeach
                                </select>
                            </form>
                        @elseif (auth()->user()->isGlobalAdministrator())
                            <span class="hidden text-xs font-semibold text-text-secondary md:inline">Escopo global</span>
                        @endif
                        <a class="hidden items-center gap-2.5 rounded-full border border-border-default bg-surface-card px-2.5 py-1.5 transition hover:border-brand-sky hover:bg-surface-muted sm:flex" href="{{ route('profile.edit') }}" aria-label="Abrir meu perfil">
                            @if (auth()->user()->profile_photo_path)
                                <img class="size-7 shrink-0 rounded-full object-cover" src="{{ asset('storage/'.auth()->user()->profile_photo_path) }}" alt="">
                            @else
                                <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand-primary-soft text-xs font-semibold text-brand-primary" aria-hidden="true">{{ str(auth()->user()->display_name)->substr(0, 1)->upper() }}</span>
                            @endif
                            <div class="min-w-0 pr-1">
                                <p class="max-w-40 truncate text-xs font-semibold text-text-primary">{{ auth()->user()->display_name }}</p>
                                <p class="max-w-40 truncate text-[11px] text-text-secondary">{{ '@'.auth()->user()->username }}</p>
                            </div>
                        </a>
                        <a class="grid size-9 place-items-center rounded-full border border-border-default bg-surface-card sm:hidden" href="{{ route('profile.edit') }}" aria-label="Abrir meu perfil">
                            @if (auth()->user()->profile_photo_path)
                                <img class="size-7 rounded-full object-cover" src="{{ asset('storage/'.auth()->user()->profile_photo_path) }}" alt="">
                            @else
                                <span class="grid size-7 place-items-center rounded-full bg-brand-primary-soft text-xs font-semibold text-brand-primary" aria-hidden="true">{{ str(auth()->user()->display_name)->substr(0, 1)->upper() }}</span>
                            @endif
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="ui-button-outline px-3 py-2 text-xs" type="submit">Sair</button>
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

                @if (session('warning'))
                    <div class="mb-6 flex items-start justify-between gap-4 rounded-2xl border border-warning/20 bg-warning-soft px-4 py-3 text-sm text-warning" role="status">
                        <p>{{ session('warning') }}</p>
                        <button class="rounded-md p-1 hover:bg-warning/10" type="button" data-flash-dismiss aria-label="Fechar aviso">
                            <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5 5 10 10M15 5 5 15" /></svg>
                        </button>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <aside id="mobile-sidebar" class="fixed z-50 flex h-dvh w-[15rem] max-w-[85vw] -translate-x-full flex-col bg-surface-card shadow-2xl lg:hidden" tabindex="-1" aria-label="Navegação principal" aria-hidden="true" data-mobile-sidebar>
        <div class="flex h-16 items-center justify-between border-b border-border-default px-4">
            <x-application-logo :href="route('dashboard')" />
            <button class="grid size-9 place-items-center rounded-xl text-text-secondary transition hover:bg-surface-muted hover:text-brand-primary" type="button" aria-label="Fechar navegação" data-mobile-sidebar-close>
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>
            </button>
        </div>
        <nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="Navegação principal no celular">
            <x-navigation.sidebar-links context="mobile" />
        </nav>
    </aside>
</body>
</html>
