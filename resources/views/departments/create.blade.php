@extends('layouts.app')
@section('title', 'Novo departamento')
@section('header', 'Novo departamento')
@section('content')
<div class="mx-auto grid max-w-2xl gap-6"><a class="text-sm font-semibold text-brand-primary" href="{{ route('departments.index') }}">← Voltar</a><header><p class="ui-page-kicker">Administração</p><h1 class="mt-1 ui-page-title">Novo departamento</h1></header><form class="ui-card grid gap-5 p-6 sm:p-8" method="POST" action="{{ route('departments.store') }}">@csrf @include('departments._fields')<div class="flex justify-end gap-3"><a class="ui-button-outline" href="{{ route('departments.index') }}">Cancelar</a><button class="ui-button-primary">Criar departamento</button></div></form></div>
@endsection
