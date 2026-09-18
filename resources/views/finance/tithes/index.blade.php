@extends('layouts.app')

@section('title', 'Dízimos')
@section('header', 'Dízimos')

@section('content')
    <div class="mx-auto grid max-w-7xl gap-7">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="ui-page-kicker">Financeiro</p>
                <h1 class="mt-1 ui-page-title">Dízimos</h1>
                <p class="mt-2 ui-page-copy">Acompanhe e registre os recebimentos por membro e igreja.</p>
            </div>
        </header>

        <section class="ui-card overflow-hidden">
            <form class="flex flex-wrap items-end gap-4 border-b border-border-default bg-surface-muted/60 p-6" method="GET" action="{{ route('finance.tithes.index') }}">
                @php $months = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro']; @endphp
                <div>
                    <label class="ui-label text-xs" for="reference_month">Mês de referência</label>
                    <select class="ui-select w-auto" id="reference_month" name="reference_month" data-autosize-select data-select-min-width="8" data-select-max-width="12">
                        @foreach ($months as $number => $name)<option value="{{ $number }}" @selected($referenceMonth->month === $number)>{{ $name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="ui-label text-xs" for="reference_year">Ano de referência</label>
                    <select class="ui-select w-auto" id="reference_year" name="reference_year" data-autosize-select data-select-min-width="6" data-select-max-width="8">
                        @for ($year = today()->year - 10; $year <= today()->year; $year++)<option value="{{ $year }}" @selected($referenceMonth->year === $year)>{{ $year }}</option>@endfor
                    </select>
                </div>
                <button class="ui-button-primary" type="submit">Filtrar</button>
            </form>

            @if ($members->isEmpty())
                <div class="px-6 py-16 text-center"><h2 class="font-semibold text-text-primary">Nenhum membro ativo nesta igreja</h2><p class="mt-2 text-sm text-text-secondary">Altere a igreja selecionada ou cadastre um vínculo ativo.</p></div>
            @else
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-border-default bg-surface-muted text-xs font-semibold tracking-wide text-text-secondary uppercase">
                            <tr><th class="px-7 py-3">Membro</th><th class="px-5 py-3">Usuário</th><th class="px-5 py-3">Valor do dízimo</th><th class="px-5 py-3">Data de pagamento</th><th class="px-5 py-3">Referência</th><th class="px-7 py-3 text-right">Ações</th></tr>
                        </thead>
                        <tbody class="divide-y divide-border-default/70">
                            @foreach ($members as $member)
                                @php $tithe = $member->financialTransactions->first(); @endphp
                                <tr>
                                    <td class="px-7 py-4"><p class="font-semibold text-text-primary">{{ $member->name }}</p><p class="mt-1 text-xs text-text-secondary">{{ $church->name }}</p></td>
                                    <td class="px-5 py-4 text-text-secondary">{{ $member->user ? $member->user->display_name.' · @'.$member->user->username : 'Sem acesso' }}</td>
                                    @if ($tithe)
                                        <td class="px-5 py-4 font-semibold text-success">R$ {{ number_format((float) $tithe->amount, 2, ',', '.') }}</td>
                                        <td class="px-5 py-4 text-text-secondary">{{ $tithe->occurred_on->format('d/m/Y') }}</td>
                                        <td class="px-5 py-4 text-text-secondary">{{ $tithe->competence_month->format('m/Y') }}</td>
                                        <td class="px-7 py-4"><div class="flex flex-wrap justify-end gap-2"><a class="ui-button-secondary rounded-lg px-3 py-2 text-xs" href="{{ route('finance.tithes.show', $tithe) }}">Visualizar</a>@if ($canWrite)<form method="POST" action="{{ route('finance.tithes.reverse', $tithe) }}" data-confirm="Tem certeza que deseja apagar este dízimo? O saldo será estornado e o histórico será preservado.">@csrf<button class="ui-button-danger rounded-lg px-3 py-2 text-xs" type="submit">Apagar</button></form>@endif</div></td>
                                    @elseif ($canWrite)
                                        @php $formId = 'tithe-'.$member->id; @endphp
                                        <td class="px-5 py-4"><input class="ui-input min-w-32 py-2 text-sm" form="{{ $formId }}" name="amount" inputmode="decimal" placeholder="R$ 0,00" required data-currency-input></td>
                                        <td class="px-5 py-4"><input class="ui-input min-w-36 py-2 text-sm" form="{{ $formId }}" name="paid_on" inputmode="numeric" pattern="\d{2}/\d{2}/\d{4}" placeholder="dd/mm/aaaa" value="{{ today()->format('d/m/Y') }}" required></td>
                                        <td class="px-5 py-4 text-text-secondary">{{ $referenceMonth->format('m/Y') }}</td>
                                        <td class="px-7 py-4 text-right"><form id="{{ $formId }}" method="POST" action="{{ route('finance.tithes.store') }}">@csrf<input type="hidden" name="church_id" value="{{ $church->id }}"><input type="hidden" name="member_id" value="{{ $member->id }}"><input type="hidden" name="competence_month" value="{{ $referenceMonth->format('Y-m') }}"><input type="hidden" name="payment_method" value="cash"><button class="ui-button-primary rounded-lg px-3 py-2 text-xs" type="submit">Registrar</button></form></td>
                                    @else
                                        <td class="px-5 py-4 text-text-secondary">—</td><td class="px-5 py-4 text-text-secondary">—</td><td class="px-5 py-4 text-text-secondary">{{ $referenceMonth->format('m/Y') }}</td><td class="px-7 py-4 text-right"><span class="text-xs text-text-secondary">Somente leitura</span></td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="grid divide-y divide-border-default/70 md:hidden">
                    @foreach ($members as $member)
                        @php $tithe = $member->financialTransactions->first(); @endphp
                        <article class="grid gap-2 p-5"><div class="flex justify-between gap-3"><h2 class="font-semibold text-text-primary">{{ $member->name }}</h2><p class="font-semibold text-success">{{ $tithe ? 'R$ '.number_format((float) $tithe->amount, 2, ',', '.') : '—' }}</p></div><p class="text-sm text-text-secondary">{{ $member->user ? '@'.$member->user->username : 'Sem usuário' }} · {{ $tithe?->occurred_on?->format('d/m/Y') ?? 'Sem pagamento no mês' }}</p>@if ($tithe)<a class="ui-button-outline w-fit px-3 py-2 text-xs" href="{{ route('finance.tithes.show', $tithe) }}">Visualizar</a>@elseif($canWrite)<form class="grid gap-2" method="POST" action="{{ route('finance.tithes.store') }}">@csrf<input type="hidden" name="church_id" value="{{ $church->id }}"><input type="hidden" name="member_id" value="{{ $member->id }}"><input type="hidden" name="competence_month" value="{{ $referenceMonth->format('Y-m') }}"><input type="hidden" name="payment_method" value="cash"><input class="ui-input" name="amount" inputmode="decimal" placeholder="R$ 0,00" required data-currency-input><input class="ui-input" name="paid_on" inputmode="numeric" pattern="\d{2}/\d{2}/\d{4}" placeholder="dd/mm/aaaa" value="{{ today()->format('d/m/Y') }}" required><button class="ui-button-primary w-fit px-3 py-2 text-xs">Registrar</button></form>@endif</article>
                    @endforeach
                </div>
                <div class="border-t border-border-default px-6 py-4">{{ $members->links() }}</div>
            @endif
        </section>
    </div>
@endsection
