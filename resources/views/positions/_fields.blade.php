@if ($errors->any())
    <div class="ui-alert-error" role="alert"><p class="font-semibold">Revise os campos informados.</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<div><label class="ui-label" for="name">Nome</label><input class="ui-input" id="name" name="name" value="{{ old('name', $position->name ?? '') }}" maxlength="255" required autofocus data-uppercase-input></div>
<div>
    <div class="flex items-center justify-between gap-3"><label class="ui-label" for="department_id">Departamento</label>@can('create', App\Models\Department::class)<button class="mb-1.5 text-xs font-semibold text-brand-primary hover:text-brand-primary-hover" type="button" data-quick-department-trigger hidden>Adicionar departamento</button>@endcan</div>
    <select class="ui-select" id="department_id" name="department_id" data-department-select><option value="">Sem departamento</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(old('department_id', $position->department_id ?? '') === $department->id)>{{ $department->name }}{{ $department->status === App\Status::Inactive ? ' (inativo)' : '' }}</option>@endforeach</select>
    <p class="mt-1 text-xs text-text-secondary">Pesquise para selecionar. Quando não existir, use “Adicionar departamento”; ele serve somente para organização.</p>
</div>
<div><label class="ui-label" for="description">Descrição</label><textarea class="ui-input min-h-24" id="description" name="description" maxlength="255">{{ old('description', $position->description ?? '') }}</textarea></div>
<label class="flex items-start gap-3 rounded-2xl border border-border-default bg-surface-muted p-4"><input class="mt-1 size-4 accent-brand-primary" type="checkbox" name="grants_system_access" value="1" @checked(old('grants_system_access', $position->grants_system_access ?? false))><span><strong class="block text-sm text-text-primary">Concede acesso ao sistema</strong><span class="mt-1 block text-xs leading-5 text-text-secondary">Somente cargos com esta opção ativa participam do login e do cálculo de permissões.</span></span></label>
@if (($position->grants_system_access ?? false) && (($impact['members'] ?? 0) > 0 || ($impact['users'] ?? 0) > 0))
    <label class="flex items-start gap-3 rounded-2xl border border-warning/20 bg-warning-soft p-4"><input class="mt-1 size-4 accent-brand-primary" type="checkbox" name="confirm_access_revocation" value="1"><span class="text-sm text-warning">Se você desmarcar o acesso, confirme a revogação para {{ $impact['members'] }} membro(s) e {{ $impact['users'] }} usuário(s). As permissões do cargo serão removidas.</span></label>
@endif
