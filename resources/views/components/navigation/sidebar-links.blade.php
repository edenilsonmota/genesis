@props(['context' => 'desktop'])

@php
    $canViewDashboard = auth()->user()->can('view-dashboard');
    $canViewOrganization = auth()->user()->can('viewAny', App\Models\Area::class);
    $canViewMembers = auth()->user()->can('viewAny', App\Models\Member::class);
    $canViewUsers = auth()->user()->can('viewAny', App\Models\User::class);
    $canViewPositions = auth()->user()->can('viewAny', App\Models\Position::class);
    $canViewDepartments = auth()->user()->can('viewAny', App\Models\Department::class);
    $canViewFinancialAccounts = auth()->user()->can('viewAny', App\Models\FinancialAccount::class);
    $canViewFinancialCategories = auth()->user()->can('viewAny', App\Models\FinancialCategory::class);
    $registrationsActive = request()->routeIs('organization.*') || request()->routeIs('members.*');
    $administrationActive = request()->routeIs('users.*') || request()->routeIs('positions.*') || request()->routeIs('departments.*');
    $financeActive = request()->routeIs('finance.*');
    $registrationsId = 'sidebar-registrations-'.$context;
    $administrationId = 'sidebar-administration-'.$context;
    $financeId = 'sidebar-finance-'.$context;
@endphp

<div class="grid gap-1" data-sidebar-links data-sidebar-context="{{ $context }}">
    @if ($canViewDashboard)
        <p class="px-2.5 pb-2 text-[10px] font-semibold tracking-[0.14em] text-text-disabled uppercase" data-sidebar-category-heading>Principal</p>
        <a @class(['sidebar-item', 'sidebar-item-active' => request()->routeIs('dashboard'), 'sidebar-item-idle' => ! request()->routeIs('dashboard')]) href="{{ route('dashboard') }}" data-sidebar-item @if(request()->routeIs('dashboard')) aria-current="page" @endif>
            <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 13h6V4H4v9Zm0 7h6v-4H4v4Zm10 0h6v-9h-6v9Zm0-16v4h6V4h-6Z" /></svg>
            <span data-sidebar-text>Visão geral</span>
        </a>
    @endif

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
                <a @class(['sidebar-item', 'sidebar-item-active' => request()->routeIs('organization.*'), 'sidebar-item-idle' => ! request()->routeIs('organization.*')]) href="{{ route('organization.index') }}" data-sidebar-item @if(request()->routeIs('organization.*')) aria-current="page" @endif>
                    <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 20h16M6 20V9l6-5 6 5v11M9 20v-5h6v5M4 9h16" /></svg><span data-sidebar-text>Área e Igrejas</span>
                </a>
            @endif
            @if ($canViewMembers)
                <a @class(['sidebar-item', 'sidebar-item-active' => request()->routeIs('members.*'), 'sidebar-item-idle' => ! request()->routeIs('members.*')]) href="{{ route('members.index') }}" data-sidebar-item @if(request()->routeIs('members.*')) aria-current="page" @endif>
                    <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M16 20v-1.5a4.5 4.5 0 0 0-4.5-4.5h-4A4.5 4.5 0 0 0 3 18.5V20m14-6a4 4 0 1 0 0-8m-7.5 4a3.5 3.5 0 1 0-7 0 3.5 3.5 0 0 0 7 0Z" /></svg><span data-sidebar-text>Membros</span>
                </a>
            @endif
        </div>
    @endif

    @if ($canViewUsers || $canViewPositions || $canViewDepartments)
        <div class="relative mt-6">
            <button class="sidebar-category-toggle" type="button" data-sidebar-category-toggle data-sidebar-category="administration" data-sidebar-context="{{ $context }}" aria-controls="{{ $administrationId }}" aria-expanded="{{ $administrationActive ? 'true' : 'false' }}">
                <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 4.5h14v15H5zM9 4.5v-2h6v2M9 10h6M9 14h6" /></svg>
                <span class="min-w-0 flex-1 font-semibold tracking-wide uppercase" data-sidebar-text>Administração</span>
                <svg class="size-3.5 shrink-0 transition-transform" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" data-sidebar-chevron><path d="m5 7.5 5 5 5-5" /></svg>
            </button>
        </div>
        <div class="grid gap-1 overflow-hidden {{ $administrationActive ? '' : 'hidden' }}" id="{{ $administrationId }}" data-sidebar-category-panel="administration" data-sidebar-category-active="{{ $administrationActive ? 'true' : 'false' }}">
            @if ($canViewUsers)
                <a @class(['sidebar-item', 'sidebar-item-active' => request()->routeIs('users.*'), 'sidebar-item-idle' => ! request()->routeIs('users.*')]) href="{{ route('users.index') }}" data-sidebar-item @if(request()->routeIs('users.*')) aria-current="page" @endif>
                    <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M16 20v-1.5a4.5 4.5 0 0 0-4.5-4.5h-4A4.5 4.5 0 0 0 3 18.5V20m14-6a4 4 0 1 0 0-8m-7.5 4a3.5 3.5 0 1 0-7 0 3.5 3.5 0 0 0 7 0Z" /></svg><span data-sidebar-text>Usuários</span>
                </a>
            @endif
            @if ($canViewPositions)
                <a @class(['sidebar-item', 'sidebar-item-active' => request()->routeIs('positions.*'), 'sidebar-item-idle' => ! request()->routeIs('positions.*')]) href="{{ route('positions.index') }}" data-sidebar-item @if(request()->routeIs('positions.*')) aria-current="page" @endif>
                    <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 7h12M8 7V5h8v2m-9 0-1 13h12L17 7M9 11h6M9 15h6" /></svg><span data-sidebar-text>Cargos e permissões</span>
                </a>
            @endif
            @if ($canViewDepartments)
                <a @class(['sidebar-item', 'sidebar-item-active' => request()->routeIs('departments.*'), 'sidebar-item-idle' => ! request()->routeIs('departments.*')]) href="{{ route('departments.index') }}" data-sidebar-item @if(request()->routeIs('departments.*')) aria-current="page" @endif>
                    <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 5h16v14H4zM8 9h3v3H8zM13 9h3M13 12h3M8 15h8" /></svg><span data-sidebar-text>Departamentos</span>
                </a>
            @endif
        </div>
    @endif

    @if ($canViewFinancialAccounts || $canViewFinancialCategories)
        <div class="relative mt-6">
            <button class="sidebar-category-toggle" type="button" data-sidebar-category-toggle data-sidebar-category="finance" data-sidebar-context="{{ $context }}" aria-controls="{{ $financeId }}" aria-expanded="{{ $financeActive ? 'true' : 'false' }}">
                <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7.5h16v11H4zM7 5h10v2.5M4 11h16M8 15h3" /></svg>
                <span class="min-w-0 flex-1 font-semibold tracking-wide uppercase" data-sidebar-text>Financeiro</span>
                <svg class="size-3.5 shrink-0 transition-transform" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" data-sidebar-chevron><path d="m5 7.5 5 5 5-5" /></svg>
            </button>
        </div>
        <div class="grid gap-1 overflow-hidden {{ $financeActive ? '' : 'hidden' }}" id="{{ $financeId }}" data-sidebar-category-panel="finance" data-sidebar-category-active="{{ $financeActive ? 'true' : 'false' }}">
            <a @class(['sidebar-item', 'sidebar-item-active' => $financeActive, 'sidebar-item-idle' => ! $financeActive]) href="{{ $canViewFinancialAccounts ? route('finance.accounts.index') : route('finance.categories.index') }}" data-sidebar-item @if($financeActive) aria-current="page" @endif>
                <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16v12H4zM8 10h3v4H8zM14 10h3M14 14h3" /></svg><span data-sidebar-text>Contas e categorias</span>
            </a>
        </div>
    @endif
</div>
