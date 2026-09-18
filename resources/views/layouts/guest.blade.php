<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Acesso') · {{ config('app.name', 'Genesis') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @hasSection('splitGuestLayout')
        <main class="min-h-screen bg-surface-card lg:grid lg:grid-cols-[minmax(0,1.15fr)_minmax(28rem,0.85fr)]">
            <section class="relative hidden min-h-screen overflow-hidden bg-[#020817] lg:block" aria-label="Genesis+ para gestão de igrejas">
                <img class="absolute inset-0 size-full object-cover object-center brightness-[1.08]" src="{{ asset('images/login-banner.png') }}" alt="" width="1536" height="1024" fetchpriority="high">
                <div class="absolute inset-0 bg-gradient-to-b from-[#020817]/65 via-[#020817]/5 to-[#020817]/82" aria-hidden="true"></div>
                <div class="relative flex min-h-screen flex-col justify-between p-9 pb-16 xl:p-12 xl:pb-20">
                    <x-application-logo :href="route('login')" :inverse="true" />
                    <div class="max-w-xl text-white">
                        <p class="text-sm font-semibold tracking-[0.16em] text-brand-cyan uppercase">Gestão que conecta</p>
                        <h1 class="mt-3 text-3xl font-semibold tracking-tight xl:text-4xl">Pessoas, igrejas e propósito em um só lugar.</h1>
                        <p class="mt-4 max-w-lg text-sm leading-6 text-white/75 xl:text-base">Uma visão clara da comunidade para cuidar melhor de cada pessoa e administrar com responsabilidade.</p>
                    </div>
                </div>
            </section>

            <section class="relative flex min-h-screen items-center justify-center overflow-hidden bg-surface-page px-5 py-10 sm:px-10 lg:bg-surface-card lg:px-12 xl:px-16">
                <div class="pointer-events-none absolute -right-28 -top-28 size-80 rounded-full bg-brand-cyan/15 blur-3xl lg:hidden" aria-hidden="true"></div>
                <div class="relative w-full max-w-[26rem]">
                    <div class="mb-9 lg:hidden">
                        <x-application-logo :href="route('login')" />
                    </div>
                    @yield('content')
                    <p class="mt-8 text-center text-xs text-text-secondary">Gestão responsável para comunidades que cuidam de pessoas.</p>
                </div>
            </section>
        </main>
    @else
        <main class="relative grid min-h-screen place-items-center overflow-hidden bg-surface-page px-4 py-10 sm:px-6">
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                <div class="absolute -left-28 top-0 size-96 rounded-full bg-brand-cyan/25 blur-3xl"></div>
                <div class="absolute -right-20 bottom-0 size-80 rounded-full bg-brand-primary/15 blur-3xl"></div>
            </div>

            <div class="relative w-full max-w-md">
                <div class="mb-8 flex justify-center">
                    <x-application-logo :href="route('login')" />
                </div>

                <section class="ui-card p-6 shadow-xl shadow-brand-primary/10 sm:p-8">
                    @yield('content')
                </section>

                <p class="mt-6 text-center text-xs text-text-secondary">Gestão responsável para comunidades que cuidam de pessoas.</p>
            </div>
        </main>
    @endif
</body>
</html>
