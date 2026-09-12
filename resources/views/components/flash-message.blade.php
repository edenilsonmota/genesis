@props(['message'])

<div
    {{ $attributes->merge(['class' => 'flex items-start justify-between gap-4 rounded-2xl border border-success/20 bg-success-soft px-4 py-3 text-sm text-success']) }}
    data-flash-message
    role="status"
>
    <p>{{ $message }}</p>
    <button class="rounded-md p-1 text-success hover:bg-success/10" type="button" data-flash-dismiss aria-label="Fechar mensagem">
        <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="m5 5 10 10M15 5 5 15" />
        </svg>
    </button>
</div>
