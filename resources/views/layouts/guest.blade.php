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
</body>
</html>
