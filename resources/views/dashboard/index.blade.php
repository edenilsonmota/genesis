@extends('layouts.app')

@section('title', 'Painel')
@section('header', 'Visão geral')

@section('content')
    <div class="mx-auto max-w-6xl">
        <section class="overflow-hidden rounded-3xl bg-ink-950 text-white shadow-xl shadow-slate-200">
            <div class="relative px-6 py-9 sm:px-9 sm:py-12">
                <div class="absolute -right-16 -top-20 size-64 rounded-full bg-genesis-500/20 blur-3xl" aria-hidden="true"></div>
                <div class="relative max-w-2xl">
                    <p class="mb-3 text-sm font-semibold text-genesis-100">Genesis · Administração</p>
                    <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Olá, {{ auth()->user()->display_name }}.</h1>
                    <p class="mt-4 max-w-xl text-sm leading-6 text-slate-300 sm:text-base">A fundação do seu ambiente está pronta. Os próximos módulos serão liberados à medida que a implantação avançar.</p>
                </div>
            </div>
        </section>

        <div class="mt-6 grid gap-6 md:grid-cols-2">
            <section class="rounded-3xl border border-stone-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold tracking-widest text-slate-400 uppercase">Sua conta</p>
                <dl class="mt-5 grid gap-4">
                    <div>
                        <dt class="text-xs text-slate-500">Nome</dt>
                        <dd class="mt-1 font-semibold text-ink-950">{{ auth()->user()->display_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Nome de usuário</dt>
                        <dd class="mt-1 font-semibold text-ink-950">{{ '@'.auth()->user()->username }}</dd>
                    </div>
                    @can('global-administrator')
                        <div>
                            <dt class="text-xs text-slate-500">Escopo de acesso</dt>
                            <dd class="mt-1 inline-flex rounded-full bg-genesis-50 px-3 py-1 text-sm font-semibold text-genesis-700">Administrador global</dd>
                        </div>
                    @endcan
                </dl>
            </section>

            <section class="rounded-3xl border border-stone-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold tracking-widest text-slate-400 uppercase">Próximas entregas</p>
                <h2 class="mt-3 text-xl font-semibold text-ink-950">Estrutura preparada para crescer</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">Membros, igrejas, cargos e grupos de acesso serão adicionados sem misturar dados pessoais com credenciais de autenticação.</p>
            </section>
        </div>
    </div>
@endsection
