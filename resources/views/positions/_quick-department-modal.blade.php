@can('create', App\Models\Department::class)
    <dialog class="m-auto w-[calc(100%-2rem)] max-w-md rounded-3xl border border-border-default bg-surface-card p-0 text-text-primary shadow-2xl shadow-brand-primary/15 backdrop:bg-text-primary/45 backdrop:backdrop-blur-sm" aria-labelledby="quick-department-title" data-quick-department-modal>
        <form class="grid gap-5 p-6 sm:p-7" action="{{ route('departments.store') }}" method="POST" data-quick-department-form>
            @csrf
            <div class="flex items-start justify-between gap-4"><div><p class="ui-page-kicker">Novo departamento</p><h2 class="mt-1 text-xl font-semibold" id="quick-department-title">Adicionar departamento</h2><p class="mt-2 text-sm text-text-secondary">O departamento será vinculado automaticamente à área.</p></div><button class="grid size-9 shrink-0 place-items-center rounded-xl text-text-secondary transition hover:bg-surface-muted hover:text-text-primary" type="button" aria-label="Fechar" data-quick-department-cancel>×</button></div>
            <div><label class="ui-label" for="quick_department_name">Nome</label><input class="ui-input" id="quick_department_name" name="name" maxlength="255" required data-quick-department-name data-uppercase-input><p class="mt-2 hidden text-sm text-danger" role="alert" data-quick-department-error></p></div>
            <div class="flex flex-col-reverse gap-3 border-t border-border-default pt-5 sm:flex-row sm:justify-end"><button class="ui-button-outline" type="button" data-quick-department-cancel>Cancelar</button><button class="ui-button-primary" type="submit" data-quick-department-submit>Criar e selecionar</button></div>
        </form>
    </dialog>
@endcan
