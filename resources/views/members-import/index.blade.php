@extends('layouts.app')

@section('title', 'Importação de membros')
@section('header', 'Importação de membros')

@section('content')
    <div class="mx-auto grid max-w-7xl gap-7">
        <header>
            <p class="ui-page-kicker">Cadastros</p>
            <h1 class="mt-1 ui-page-title">Importação de membros</h1>
            <p class="mt-2 ui-page-copy">Valide uma planilha antes de criar ou atualizar dados básicos dos membros de uma igreja.</p>
        </header>

        @if ($errors->any())
            <div class="rounded-2xl border border-danger/20 bg-danger-soft p-4 text-sm text-danger" role="alert">
                <p class="font-semibold">Não foi possível enviar o arquivo.</p>
                <ul class="mt-2 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="ui-card overflow-hidden">
            <div class="grid gap-5 border-b border-border-default bg-surface-muted/60 p-6 lg:grid-cols-[minmax(14rem,1fr)_auto] lg:items-end">
                <form method="GET" action="{{ route('members-import.index') }}">
                    <label class="ui-label" for="church_id">Igreja da importação</label>
                    <div class="mt-2 flex flex-col gap-3 sm:flex-row">
                        <select class="ui-select min-w-0 flex-1" id="church_id" name="church_id" data-autosize-select data-select-min-width="14" data-select-max-width="38">
                            <option value="">Selecione uma igreja</option>
                            @foreach ($churches as $church)
                                <option value="{{ $church->id }}" @selected($selectedChurchId === $church->id)>{{ $church->name }}</option>
                            @endforeach
                        </select>
                        <button class="ui-button-primary px-5" type="submit">Selecionar</button>
                    </div>
                </form>

                <div class="flex flex-wrap gap-2">
                    @if ($selectedChurchId !== '')
                        <a class="ui-button-outline" href="{{ route('members-import.templates.blank', ['church_id' => $selectedChurchId]) }}">Baixar modelo para novos membros</a>
                        @if ($canExportMembers)
                            <a class="ui-button-outline" href="{{ route('members-import.templates.update', ['church_id' => $selectedChurchId]) }}">Exportar membros para atualização</a>
                        @endif
                    @else
                        <button class="ui-button-outline" type="button" disabled>Baixar modelo para novos membros</button>
                        <button class="ui-button-outline" type="button" disabled>Exportar membros para atualização</button>
                    @endif
                </div>
            </div>

            <div class="grid gap-6 p-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(18rem,.8fr)]">
                <div>
                    <h2 class="text-lg font-semibold text-text-primary">Enviar planilha</h2>
                    <p class="mt-1 text-sm text-text-secondary">O arquivo será validado em segundo plano. Nenhum membro será alterado antes da sua confirmação.</p>
                    @if ($canWrite)
                        <form class="mt-5 grid gap-4" method="POST" action="{{ route('members-import.store') }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="church_id" value="{{ $selectedChurchId }}">
                            <div>
                                <label class="ui-label" for="file">Arquivo Excel</label>
                                <input class="ui-input mt-2 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-primary-soft file:px-3 file:py-2 file:font-semibold file:text-brand-primary" id="file" name="file" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                            </div>
                            <button class="ui-button-primary w-fit px-5" type="submit">Enviar para validação</button>
                        </form>
                    @elseif ($selectedChurchId === '')
                        <p class="mt-5 rounded-xl bg-warning-soft p-4 text-sm text-warning">Selecione uma igreja para habilitar o envio.</p>
                    @else
                        <p class="mt-5 rounded-xl bg-surface-muted p-4 text-sm text-text-secondary">Sua permissão permite consulta, mas não o envio ou a confirmação de importações.</p>
                    @endif
                </div>

                <aside class="rounded-2xl border border-border-default bg-surface-muted/50 p-5 text-sm text-text-secondary">
                    <h2 class="font-semibold text-text-primary">Antes de enviar</h2>
                    <ul class="mt-3 grid list-disc gap-2 pl-5">
                        <li>Formato aceito: <strong>.xlsx</strong>; limite de 10 MB e 5.000 linhas.</li>
                        <li>A aba deve se chamar <strong>Membros</strong> e os cabeçalhos não podem ser alterados.</li>
                        <li>Novos membros exigem nome, CPF válido e CEP para localizar a cidade.</li>
                        <li>CPF, telefone e CEP podem estar com máscara; datas usam dd/mm/aaaa.</li>
                        <li>Havendo qualquer erro, todo o arquivo fica bloqueado para confirmação.</li>
                    </ul>
                </aside>
            </div>
        </section>

        <section class="ui-card overflow-hidden">
            <div class="border-b border-border-default px-6 py-5">
                <h2 class="text-lg font-semibold text-text-primary">Histórico</h2>
                <p class="mt-1 text-sm text-text-secondary">Importações visíveis no seu escopo de igrejas.</p>
            </div>
            @if ($imports->isEmpty())
                <p class="px-6 py-12 text-center text-sm text-text-secondary">Nenhuma importação encontrada.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead class="bg-surface-muted text-xs font-semibold tracking-wide text-text-secondary uppercase">
                            <tr><th class="px-6 py-3">Data</th><th class="px-5 py-3">Arquivo</th><th class="px-5 py-3">Igreja</th><th class="px-5 py-3">Responsável</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Totais</th><th class="px-6 py-3 text-right">Ações</th></tr>
                        </thead>
                        <tbody class="divide-y divide-border-default/70">
                            @foreach ($imports as $import)
                                <tr>
                                    <td class="px-6 py-4 text-text-secondary">{{ $import->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="max-w-60 truncate px-5 py-4 font-medium text-text-primary" title="{{ $import->original_filename }}">{{ $import->original_filename }}</td>
                                    <td class="px-5 py-4 text-text-secondary">{{ $import->church->name }}</td>
                                    <td class="px-5 py-4 text-text-secondary">{{ $import->uploadedBy->display_name }}</td>
                                    <td class="px-5 py-4"><span class="rounded-full bg-brand-primary-soft px-3 py-1 text-xs font-semibold text-brand-primary">{{ $import->status->label() }}</span></td>
                                    <td class="px-5 py-4 text-xs text-text-secondary">{{ $import->new_members_count }} novos · {{ $import->updates_count }} atualizações · {{ $import->errors_count }} erros</td>
                                    <td class="px-6 py-4 text-right"><a class="ui-button-secondary rounded-lg px-3 py-2 text-xs" href="{{ route('members-import.show', $import) }}">Visualizar</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-border-default px-6 py-4">{{ $imports->links() }}</div>
            @endif
        </section>
    </div>
@endsection
