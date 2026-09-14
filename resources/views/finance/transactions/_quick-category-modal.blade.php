<dialog class="m-auto w-[calc(100%-2rem)] max-w-md rounded-3xl border border-border-default bg-surface-card p-0 text-text-primary shadow-2xl shadow-brand-primary/15 backdrop:bg-text-primary/45 backdrop:backdrop-blur-sm" aria-labelledby="quick-category-title" data-quick-category-modal>
    <form class="grid gap-5 p-6 sm:p-7" action="{{ route('finance.transactions.categories.store') }}" method="POST" data-quick-category-form>
        @csrf
        <div class="flex items-start justify-between gap-4"><div><p class="ui-page-kicker">Nova categoria</p><h2 class="mt-1 text-xl font-semibold" id="quick-category-title">Adicionar categoria</h2><p class="mt-2 text-sm text-text-secondary">Ela será criada para o tipo de movimentação e a área da conta selecionada.</p></div><button class="grid size-9 shrink-0 place-items-center rounded-xl text-text-secondary transition hover:bg-surface-muted hover:text-text-primary" type="button" aria-label="Fechar" data-quick-category-cancel>×</button></div>
        <input name="type" type="hidden" data-quick-category-type>
        <input name="area_id" type="hidden" data-quick-category-area>
        <div><label class="ui-label" for="quick_category_name">Nome</label><input class="ui-input" id="quick_category_name" name="name" maxlength="255" required data-quick-category-name><p class="mt-2 hidden text-sm text-danger" role="alert" data-quick-category-error></p></div>
        <div class="flex flex-col-reverse gap-3 border-t border-border-default pt-5 sm:flex-row sm:justify-end"><button class="ui-button-outline" type="button" data-quick-category-cancel>Cancelar</button><button class="ui-button-primary" type="submit" data-quick-category-submit>Criar e selecionar</button></div>
    </form>
</dialog>
