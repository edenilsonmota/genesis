@extends('layouts.app')

@section('title', 'Painel')
@section('header', 'Visão geral')

@section('content')
    <div class="mx-auto max-w-6xl">
        <section class="overflow-hidden rounded-3xl bg-brand-gradient text-white shadow-xl shadow-brand-primary/20">
            <div class="relative px-6 py-9 sm:px-9 sm:py-12">
                <div class="absolute -right-16 -top-20 size-64 rounded-full bg-brand-cyan/25 blur-3xl" aria-hidden="true"></div>
                <div class="relative max-w-2xl">
                    <p class="mb-3 text-sm font-semibold text-white/80">Genesis+ · Administração</p>
                    <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Olá, {{ auth()->user()->display_name }}.</h1>
                    <p class="mt-4 max-w-xl text-sm leading-6 text-white/80 sm:text-base">A fundação do seu ambiente está pronta. Os próximos módulos serão liberados à medida que a implantação avançar.</p>
                </div>
            </div>
        </section>

        <div class="mt-6 grid gap-6 md:grid-cols-2">
            <section class="ui-card p-6">
                <p class="text-xs font-semibold tracking-widest text-text-disabled uppercase">Sua conta</p>
                <dl class="mt-5 grid gap-4">
                    <div>
                        <dt class="text-xs text-text-secondary">Nome</dt>
                        <dd class="mt-1 font-semibold text-text-primary">{{ auth()->user()->display_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-text-secondary">Nome de usuário</dt>
                        <dd class="mt-1 font-semibold text-text-primary">{{ '@'.auth()->user()->username }}</dd>
                    </div>
                    @can('global-administrator')
                        <div>
                            <dt class="text-xs text-text-secondary">Escopo de acesso</dt>
                            <dd class="mt-1 inline-flex rounded-full bg-brand-primary/10 px-3 py-1 text-sm font-semibold text-brand-primary">Administrador global</dd>
                        </div>
                    @endcan
                </dl>
            </section>

            <section class="ui-card p-6">
                <p class="text-xs font-semibold tracking-widest text-text-disabled uppercase">Próximas entregas</p>
                <h2 class="mt-3 text-xl font-semibold text-text-primary">Estrutura preparada para crescer</h2>
                <p class="mt-2 text-sm leading-6 text-text-secondary">Membros, igrejas, cargos e grupos de acesso serão adicionados sem misturar dados pessoais com credenciais de autenticação.</p>
            </section>
        </div>
    </div>
@endsection
