@extends('layouts.app')
@section('title', 'Editar cargo')
@section('header', 'Editar cargo')
@section('content')
<div class="mx-auto grid max-w-2xl gap-6"><a class="text-sm font-semibold text-brand-primary" href="{{ route('positions.index') }}">← Voltar</a><header><p class="ui-page-kicker">Administração</p><h1 class="mt-1 ui-page-title">Editar cargo</h1><p class="mt-2 ui-page-copy">{{ $impact['members'] }} membro(s) e {{ $impact['users'] }} usuário(s) possuem este cargo ativo.</p></header><form class="ui-card grid gap-5 p-6 sm:p-8" method="POST" action="{{ route('positions.update', $position) }}">@csrf @method('PUT') @include('positions._fields')<div class="flex justify-end gap-3"><a class="ui-button-outline" href="{{ route('positions.index') }}">Cancelar</a><button class="ui-button-primary">Salvar alterações</button></div></form></div>
@include('positions._quick-department-modal')
@endsection
