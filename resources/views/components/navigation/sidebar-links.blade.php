@props(['context' => 'desktop'])

@php
    $canViewOrganization = auth()->user()->can('viewAny', App\Models\Area::class);
    $canViewMembers = auth()->user()->can('viewAny', App\Models\Member::class);
    $registrationsActive = request()->routeIs('organization.*') || request()->routeIs('members.*');
    $registrationsId = 'sidebar-registrations-'.$context;
@endphp

<div class="grid gap-1" data-sidebar-links data-sidebar-context="{{ $context }}">
    <p class="px-2.5 pb-2 text-[10px] font-semibold tracking-[0.14em] text-text-disabled uppercase" data-sidebar-category-heading>Principal</p>
    <div class="relative">
        <a @class([
            'sidebar-item',
            'sidebar-item-active' => request()->routeIs('dashboard'),
            'sidebar-item-idle' => ! request()->routeIs('dashboard'),
        ]) href="{{ route('dashboard') }}" data-sidebar-item @if (request()->routeIs('dashboard')) aria-current="page" @endif>
            <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 13h6V4H4v9Zm0 7h6v-4H4v4Zm10 0h6v-9h-6v9Zm0-16v4h6V4h-6Z" /></svg>
            <span data-sidebar-text>Visão geral</span>
        </a>
    </div>

    @if ($canViewOrganization || $canViewMembers)
        <div class="relative mt-6">
            <button class="sidebar-category-toggle" type="button" data-sidebar-category-toggle data-sidebar-category="registrations" data-sidebar-context="{{ $context }}" aria-controls="{{ $registrationsId }}" aria-expanded="{{ $registrationsActive ? 'true' : 'false' }}">
                <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 7.5A2.5 2.5 0 0 1 5.5 5h4l2 2h7A2.5 2.5 0 0 1 21 9.5v7a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 16.5v-9Z" /></svg>
                <span class="min-w-0 flex-1 font-semibold tracking-wide uppercase" data-sidebar-text>Cadastros</span>
                <svg class="size-3.5 shrink-0 transition-transform" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" data-sidebar-chevron><path d="m5 7.5 5 5 5-5" /></svg>
            </button>
        </div>

        <div class="grid gap-1 overflow-hidden {{ $registrationsActive ? '' : 'hidden' }}" id="{{ $registrationsId }}" data-sidebar-category-panel="registrations" data-sidebar-category-active="{{ $registrationsActive ? 'true' : 'false' }}">
            @if ($canViewOrganization)
                <div class="relative">
                    <a @class([
                        'sidebar-item',
                        'sidebar-item-active' => request()->routeIs('organization.*'),
                        'sidebar-item-idle' => ! request()->routeIs('organization.*'),
                    ]) href="{{ route('organization.index') }}" data-sidebar-item @if (request()->routeIs('organization.*')) aria-current="page" @endif>
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 20h16M6 20V9l6-5 6 5v11M9 20v-5h6v5M4 9h16" /></svg>
                        <span data-sidebar-text>Área e Igrejas</span>
                    </a>
                </div>
            @endif

            @if ($canViewMembers)
                <div class="relative">
                    <a @class([
                        'sidebar-item',
                        'sidebar-item-active' => request()->routeIs('members.*'),
                        'sidebar-item-idle' => ! request()->routeIs('members.*'),
                    ]) href="{{ route('members.index') }}" data-sidebar-item @if (request()->routeIs('members.*')) aria-current="page" @endif>
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M16 20v-1.5a4.5 4.5 0 0 0-4.5-4.5h-4A4.5 4.5 0 0 0 3 18.5V20m14-6a4 4 0 1 0 0-8m-7.5 4a3.5 3.5 0 1 0-7 0 3.5 3.5 0 0 0 7 0Z" /></svg>
                        <span data-sidebar-text>Membros</span>
                    </a>
                </div>
            @endif
        </div>
    @endif
</div>
