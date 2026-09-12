@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        <div class="flex items-center justify-between gap-3 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="ui-button-outline cursor-not-allowed opacity-55" aria-disabled="true">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a class="ui-button-outline" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a class="ui-button-outline" href="{{ $paginator->nextPageUrl() }}" rel="next">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="ui-button-outline cursor-not-allowed opacity-55" aria-disabled="true">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>

        <div class="hidden items-center justify-between gap-5 sm:flex">
            <p class="text-sm text-text-secondary">
                {!! __('Showing') !!}
                @if ($paginator->firstItem())
                    <span class="font-semibold text-text-primary">{{ $paginator->firstItem() }}</span>
                    {!! __('to') !!}
                    <span class="font-semibold text-text-primary">{{ $paginator->lastItem() }}</span>
                @else
                    {{ $paginator->count() }}
                @endif
                {!! __('of') !!}
                <span class="font-semibold text-text-primary">{{ $paginator->total() }}</span>
                {!! __('results') !!}
            </p>

            <div class="inline-flex overflow-hidden rounded-xl border border-border-default bg-surface-card shadow-sm">
                @if ($paginator->onFirstPage())
                    <span class="grid size-10 cursor-not-allowed place-items-center text-text-disabled" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                        <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 0 1 0 1.414L9.414 10l3.293 3.293a1 1 0 0 1-1.414 1.414l-4-4a1 1 0 0 1 0-1.414l4-4a1 1 0 0 1 1.414 0Z" clip-rule="evenodd" /></svg>
                    </span>
                @else
                    <a class="grid size-10 place-items-center border-r border-border-default text-text-secondary transition hover:bg-surface-muted hover:text-brand-primary" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}">
                        <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 0 1 0 1.414L9.414 10l3.293 3.293a1 1 0 0 1-1.414 1.414l-4-4a1 1 0 0 1 0-1.414l4-4a1 1 0 0 1 1.414 0Z" clip-rule="evenodd" /></svg>
                    </a>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="grid min-w-10 place-items-center border-r border-border-default px-3 text-sm text-text-disabled" aria-disabled="true">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page === $paginator->currentPage())
                                <span class="grid size-10 place-items-center border-r border-brand-primary bg-brand-primary text-sm font-semibold text-white" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="grid size-10 place-items-center border-r border-border-default text-sm font-medium text-text-secondary transition hover:bg-surface-muted hover:text-brand-primary" href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a class="grid size-10 place-items-center text-text-secondary transition hover:bg-surface-muted hover:text-brand-primary" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}">
                        <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 0 1 0-1.414L10.586 10 7.293 6.707a1 1 0 0 1 1.414-1.414l4 4a1 1 0 0 1 0 1.414l-4 4a1 1 0 0 1-1.414 0Z" clip-rule="evenodd" /></svg>
                    </a>
                @else
                    <span class="grid size-10 cursor-not-allowed place-items-center text-text-disabled" aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                        <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 0 1 0-1.414L10.586 10 7.293 6.707a1 1 0 0 1 1.414-1.414l4 4a1 1 0 0 1 0 1.414l-4 4a1 1 0 0 1-1.414 0Z" clip-rule="evenodd" /></svg>
                    </span>
                @endif
            </div>
        </div>
    </nav>
@endif
