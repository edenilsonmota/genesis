@props(['status'])

@php
    $value = $status instanceof BackedEnum ? $status->value : $status;
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold',
    'bg-genesis-50 text-genesis-700' => $value === 'active',
    'bg-stone-100 text-slate-600' => $value === 'inactive',
]) }}>
    <span @class(['size-1.5 rounded-full', 'bg-genesis-500' => $value === 'active', 'bg-stone-400' => $value === 'inactive']) aria-hidden="true"></span>
    {{ $value === 'active' ? 'Ativo' : 'Inativo' }}
</span>
