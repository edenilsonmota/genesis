@if ($errors->any())
    <div class="ui-alert-error" role="alert">
        <p class="font-semibold">Revise os campos informados.</p>
        <ul class="mt-2 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<input type="hidden" name="area_id" value="{{ old('area_id', $financialCategory->area_id ?? $area->id) }}">

<div>
    <label class="ui-label" for="name">Nome</label>
    <input class="ui-input" id="name" name="name" value="{{ old('name', $financialCategory->name ?? '') }}" maxlength="255" required autofocus>
</div>

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label class="ui-label" for="type">Tipo</label>
        <select class="ui-select" id="type" name="type" required>
            @foreach ($categoryTypes as $categoryType)
                <option value="{{ $categoryType->value }}" @selected(old('type', isset($financialCategory) ? $financialCategory->type->value : '') === $categoryType->value)>{{ $categoryType->label() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="ui-label" for="status">Status</label>
        <select class="ui-select" id="status" name="status" required>
            <option value="active" @selected(old('status', isset($financialCategory) ? $financialCategory->status->value : 'active') === 'active')>Ativa</option>
            <option value="inactive" @selected(old('status', isset($financialCategory) ? $financialCategory->status->value : 'active') === 'inactive')>Inativa</option>
        </select>
    </div>
</div>

<div>
    <label class="ui-label" for="description">Descrição</label>
    <textarea class="ui-input min-h-28" id="description" name="description" maxlength="2000">{{ old('description', $financialCategory->description ?? '') }}</textarea>
    <p class="mt-1.5 text-xs text-text-secondary">Transferências terão tipo próprio e não serão classificadas como categoria.</p>
</div>
