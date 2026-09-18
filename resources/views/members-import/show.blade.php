@extends('layouts.app')

@section('title', 'Detalhes da importação')
@section('header', 'Importação de membros')

@section('content')
    <div class="mx-auto grid max-w-7xl gap-7">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a class="text-sm font-semibold text-brand-primary hover:underline" href="{{ route('members-import.index', ['church_id' => $memberImport->church_id]) }}">← Voltar ao histórico</a>
                <h1 class="mt-3 ui-page-title">Prévia da importação</h1>
                <p class="mt-2 ui-page-copy">Confira a validação antes de autorizar qualquer alteração nos membros.</p>
            </div>
            <span class="w-fit rounded-full bg-brand-primary-soft px-4 py-2 text-sm font-semibold text-brand-primary">{{ $memberImport->status->label() }}</span>
        </header>

        @if ($errors->any())
            <div class="rounded-2xl border border-danger/20 bg-danger-soft p-4 text-sm text-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="ui-card p-6">
            <dl class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Igreja</dt><dd class="mt-1 font-semibold text-text-primary">{{ $memberImport->church->name }}</dd></div>
                <div><dt class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Arquivo</dt><dd class="mt-1 break-all text-text-primary">{{ $memberImport->original_filename }}</dd></div>
                <div><dt class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Enviado por</dt><dd class="mt-1 text-text-primary">{{ $memberImport->uploadedBy->display_name }}</dd></div>
                <div><dt class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Data</dt><dd class="mt-1 text-text-primary">{{ $memberImport->created_at->format('d/m/Y H:i') }}</dd></div>
            </dl>
            <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl bg-surface-muted p-4"><p class="text-xs font-semibold text-text-secondary uppercase">Linhas analisadas</p><p class="mt-2 text-2xl font-bold text-text-primary">{{ $memberImport->total_rows }}</p></div>
                <div class="rounded-2xl bg-success-soft p-4"><p class="text-xs font-semibold text-success uppercase">Novos membros válidos</p><p class="mt-2 text-2xl font-bold text-success">{{ $memberImport->new_members_count }}</p></div>
                <div class="rounded-2xl bg-brand-primary-soft p-4"><p class="text-xs font-semibold text-brand-primary uppercase">Atualizações válidas</p><p class="mt-2 text-2xl font-bold text-brand-primary">{{ $memberImport->updates_count }}</p></div>
                <div class="rounded-2xl bg-danger-soft p-4"><p class="text-xs font-semibold text-danger uppercase">Linhas com erro</p><p class="mt-2 text-2xl font-bold text-danger">{{ $memberImport->errors_count }}</p></div>
            </div>

            @if ($memberImport->failure_reason)
                <p class="mt-5 rounded-xl border border-danger/20 bg-danger-soft p-4 text-sm text-danger">{{ $memberImport->failure_reason }}</p>
            @endif

            <div class="mt-6 flex flex-wrap gap-2">
                <a class="ui-button-outline" href="{{ route('members-import.original', $memberImport) }}">Baixar arquivo original</a>
                @if ($memberImport->errors_count > 0)
                    <a class="ui-button-outline" href="{{ route('members-import.errors', $memberImport) }}">Baixar relatório de erros</a>
                @endif
                @can('confirm', $memberImport)
                    @if ($memberImport->status === App\Enums\MemberImportStatus::AwaitingConfirmation && $memberImport->errors_count === 0)
                        <button class="ui-button-primary" type="button" onclick="document.getElementById('confirm-import').showModal()">Confirmar importação</button>
                    @endif
                @endcan
            </div>
        </section>

        @if ($memberImport->errors_count > 0)
            <section class="ui-card overflow-hidden">
                <div class="border-b border-border-default px-6 py-5">
                    <h2 class="text-lg font-semibold text-text-primary">Erros encontrados</h2>
                    <p class="mt-1 text-sm text-text-secondary">A importação inteira está bloqueada. Corrija o arquivo e envie uma nova importação.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[700px] text-left text-sm">
                        <thead class="bg-surface-muted text-xs font-semibold tracking-wide text-text-secondary uppercase"><tr><th class="px-6 py-3">Linha</th><th class="px-5 py-3">Campo</th><th class="px-5 py-3">Mensagem</th><th class="px-6 py-3">Valor seguro</th></tr></thead>
                        <tbody class="divide-y divide-border-default/70">
                            @foreach ($rows as $row)
                                @foreach ($row->errors ?? [] as $error)
                                    <tr><td class="px-6 py-4 font-semibold text-text-primary">{{ $row->row_number }}</td><td class="px-5 py-4 text-text-secondary">{{ $error['field'] ?? 'linha' }}</td><td class="px-5 py-4 text-danger">{{ $error['message'] ?? 'Erro de validação.' }}</td><td class="px-6 py-4 text-text-secondary">{{ $error['value'] ?? '—' }}</td></tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-border-default px-6 py-4">{{ $rows->links() }}</div>
            </section>
        @elseif (in_array($memberImport->status, [App\Enums\MemberImportStatus::Uploaded, App\Enums\MemberImportStatus::Validating, App\Enums\MemberImportStatus::Processing], true))
            <section class="ui-card p-8 text-center">
                <div class="mx-auto size-9 animate-spin rounded-full border-4 border-brand-primary-soft border-t-brand-primary" aria-hidden="true"></div>
                <h2 class="mt-4 font-semibold text-text-primary">Processamento em segundo plano</h2>
                <p class="mt-2 text-sm text-text-secondary">Esta página será atualizada automaticamente enquanto o worker Redis conclui a etapa.</p>
            </section>
            <script>window.setTimeout(() => window.location.reload(), 5000);</script>
        @endif
    </div>

    @if ($memberImport->status === App\Enums\MemberImportStatus::AwaitingConfirmation && $memberImport->errors_count === 0)
        <dialog class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-3xl border border-border-default bg-surface-card p-0 shadow-2xl backdrop:bg-text-primary/40" id="confirm-import">
            <form class="p-6" method="POST" action="{{ route('members-import.confirm', $memberImport) }}">
                @csrf
                <h2 class="text-xl font-bold text-text-primary">Confirmar importação?</h2>
                <p class="mt-3 text-sm leading-6 text-text-secondary">Serão criados {{ $memberImport->new_members_count }} membros e atualizados {{ $memberImport->updates_count }} cadastros básicos. Vínculos existentes, usuários, cargos e permissões não serão alterados.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button class="ui-button-outline" type="button" onclick="document.getElementById('confirm-import').close()">Cancelar</button>
                    <button class="ui-button-primary" type="submit">Sim, processar</button>
                </div>
            </form>
        </dialog>
    @endif
@endsection
