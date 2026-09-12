@extends('layouts.app')
@section('title', 'Novo cargo')
@section('header', 'Novo cargo')
@section('content')
<div class="mx-auto grid max-w-2xl gap-6"><a class="text-sm font-semibold text-brand-primary" href="{{ route('positions.index') }}">← Voltar</a><header><p class="ui-page-kicker">Administração</p><h1 class="mt-1 ui-page-title">Novo cargo</h1></header><form class="ui-card grid gap-5 p-6 sm:p-8" method="POST" action="{{ route('positions.store') }}">@csrf @include('positions._fields', ['position' => new App\Models\Position, 'impact' => ['members' => 0, 'users' => 0]])<div class="flex justify-end gap-3"><a class="ui-button-outline" href="{{ route('positions.index') }}">Cancelar</a><button class="ui-button-primary">Criar cargo</button></div></form></div>
@include('positions._quick-department-modal')
@endsection
