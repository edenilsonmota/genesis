@extends('layouts.app')

@section('title', 'Cadastrar membro')
@section('header', 'Cadastrar membro')

@section('content')
    <div class="mx-auto grid max-w-4xl gap-6">
        <div><a class="text-sm font-semibold text-brand-primary" href="{{ route('members.index') }}">← Voltar para membros</a><h1 class="mt-3 ui-page-title">Cadastrar membro</h1><p class="mt-2 ui-page-copy">Primeiro informe os dados pessoais; depois defina a igreja inicial.</p></div>
        @include('members._validation-errors')

        @if ($churches->isEmpty())
            <section class="rounded-3xl border border-warning/20 bg-warning-soft p-7 text-warning"><h2 class="font-semibold">Nenhuma igreja ativa disponível</h2><p class="mt-2 text-sm">Cadastre ou ative uma igreja antes de cadastrar membros.</p></section>
        @else
            @php $initialStep = $errors->hasAny(['church_id', 'joined_at', 'is_primary']) ? 2 : 1; @endphp
            <form class="ui-card p-6 sm:p-8" method="POST" action="{{ route('members.store') }}" data-member-steps data-initial-step="{{ $initialStep }}">
                @csrf
                <ol class="mb-8 grid grid-cols-2 gap-3" aria-label="Etapas do cadastro">
                    <li class="rounded-xl px-4 py-3 text-center text-sm font-semibold" data-step-indicator="1">1. Dados do membro</li>
                    <li class="rounded-xl px-4 py-3 text-center text-sm font-semibold" data-step-indicator="2">2. Igreja inicial</li>
                </ol>
                <section data-step-panel="1">
                    @include('members._fields')
                    <div class="mt-7 flex justify-end"><button class="ui-button-primary px-5" type="button" data-step-next>Continuar</button></div>
                </section>
                <section class="hidden" data-step-panel="2">
                    <h2 class="text-xl font-semibold text-text-primary">Vínculo inicial</h2>
                    <p class="mt-2 text-sm text-text-secondary">O primeiro vínculo será criado como ativo e principal.</p>
                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <div><label class="ui-label" for="church_id">Igreja <span class="text-danger">*</span></label><select class="ui-select" id="church_id" name="church_id" required><option value="">Selecione</option>@foreach ($churches as $church)<option value="{{ $church->id }}" @selected(old('church_id') === $church->id)>{{ $church->name }}</option>@endforeach</select>@error('church_id') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror</div>
                        <div><label class="ui-label" for="joined_at">Data de entrada <span class="text-danger">*</span></label><input class="ui-input" id="joined_at" name="joined_at" type="date" max="{{ today()->toDateString() }}" value="{{ old('joined_at', today()->toDateString()) }}" required>@error('joined_at') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror</div>
                    </div>
                    <input type="hidden" name="is_primary" value="1">
                    <div class="mt-7 flex flex-col-reverse gap-3 border-t border-border-default pt-5 sm:flex-row sm:justify-between"><button class="ui-button-outline px-5" type="button" data-step-previous>Voltar</button><button class="ui-button-primary px-5" type="submit">Cadastrar membro</button></div>
                </section>
            </form>
        @endif
    </div>
@endsection
