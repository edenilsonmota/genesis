@props(['href' => null, 'showName' => true, 'inverse' => false])

<a {{ $attributes->class(['inline-flex items-center gap-3']) }} href="{{ $href ?? route('dashboard') }}" aria-label="Genesis+">
    <img class="size-10 shrink-0 object-contain" src="{{ asset('images/logo.png') }}" alt="Logo Genesis+" width="40" height="40">
    @if ($showName)
        <span @class(['text-lg font-semibold tracking-tight', 'text-white' => $inverse, 'text-text-primary' => ! $inverse]) data-sidebar-logo-name>Genesis+</span>
    @endif
</a>
