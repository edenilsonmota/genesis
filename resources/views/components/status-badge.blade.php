@props(['status'])

@php
    $value = $status instanceof BackedEnum ? $status->value : $status;
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold',
    'bg-success-soft text-success' => $value === 'active',
    'bg-surface-muted text-text-secondary' => $value === 'inactive',
]) }}>
    <span @class(['size-1.5 rounded-full', 'bg-success' => $value === 'active', 'bg-text-disabled' => $value === 'inactive']) aria-hidden="true"></span>
    {{ $value === 'active' ? 'Ativo' : 'Inativo' }}
</span>
