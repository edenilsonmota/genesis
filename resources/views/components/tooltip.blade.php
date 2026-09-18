@props(['id' => null, 'text'])

@php
    $tooltipId = $id ?: 'tooltip-'.\Illuminate\Support\Str::uuid();
@endphp

<span class="group relative inline-flex">
    <button class="grid size-4 place-items-center rounded-full border border-border-default text-[10px] font-bold text-text-secondary transition hover:border-brand-sky hover:text-brand-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cyan" type="button" aria-describedby="{{ $tooltipId }}" aria-label="Mais informações">
        <span aria-hidden="true">?</span>
    </button>
    <span class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 w-64 -translate-x-1/2 rounded-lg bg-text-primary px-3 py-2 text-xs font-normal leading-5 text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100 group-focus-within:opacity-100" id="{{ $tooltipId }}" role="tooltip">{{ $text }}</span>
</span>
