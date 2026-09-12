@if ($errors->any())
    <div class="ui-alert-error" role="alert">
        <p class="font-semibold">Revise os campos informados.</p>
        <ul class="mt-2 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

@php
    $selectedOwnerType = old('owner_type', $defaultOwnerType);
@endphp

<div class="grid gap-5 sm:grid-cols-2" data-financial-owner-fields>
    <div>
        <label class="ui-label" for="owner_type">Tipo do proprietário</label>
        <select class="ui-select" id="owner_type" name="owner_type" required data-financial-owner-type>
            @if (auth()->user()->isGlobalAdministrator())
                <option value="area" @selected($selectedOwnerType === 'area')>Área</option>
            @endif
            <option value="church" @selected($selectedOwnerType === 'church')>Igreja</option>
        </select>
    </div>

    <div data-financial-area-field @if($selectedOwnerType !== 'area') hidden @endif>
        <label class="ui-label" for="area_id">Área</label>
        <select class="ui-select" id="area_id" name="area_id" @disabled($selectedOwnerType !== 'area') @if($selectedOwnerType === 'area') required @endif>
            @if ($area)
                <option value="{{ $area->id }}" @selected(old('area_id', $financialAccount->area_id ?? $area->id) === $area->id)>{{ $area->name }}</option>
            @endif
        </select>
    </div>

    <div data-financial-church-field @if($selectedOwnerType !== 'church') hidden @endif>
        <label class="ui-label" for="church_id">Igreja</label>
        <select class="ui-select" id="church_id" name="church_id" @disabled($selectedOwnerType !== 'church') @if($selectedOwnerType === 'church') required @endif>
            <option value="">Selecione a igreja</option>
            @foreach ($churches as $church)
                <option value="{{ $church->id }}" @selected(old('church_id', $financialAccount->church_id ?? '') === $church->id)>{{ $church->name }}</option>
            @endforeach
        </select>
    </div>
</div>

<div>
    <label class="ui-label" for="name">Nome</label>
    <input class="ui-input" id="name" name="name" value="{{ old('name', $financialAccount->name ?? '') }}" maxlength="255" required autofocus>
</div>

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label class="ui-label" for="type">Tipo da conta</label>
        <select class="ui-select" id="type" name="type" required>
            @foreach ($accountTypes as $accountType)
                <option value="{{ $accountType->value }}" @selected(old('type', isset($financialAccount) ? $financialAccount->type->value : '') === $accountType->value)>{{ $accountType->label() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="ui-label" for="status">Status</label>
        <select class="ui-select" id="status" name="status" required>
            <option value="active" @selected(old('status', isset($financialAccount) ? $financialAccount->status->value : 'active') === 'active')>Ativa</option>
            <option value="inactive" @selected(old('status', isset($financialAccount) ? $financialAccount->status->value : 'active') === 'inactive')>Inativa</option>
        </select>
    </div>
</div>

<div>
    <label class="ui-label" for="institution">Instituição</label>
    <input class="ui-input" id="institution" name="institution" value="{{ old('institution', $financialAccount->institution ?? '') }}" maxlength="255" placeholder="Opcional">
</div>

<div>
    <label class="ui-label" for="description">Descrição</label>
    <textarea class="ui-input min-h-28" id="description" name="description" maxlength="2000">{{ old('description', $financialAccount->description ?? '') }}</textarea>
    <p class="mt-1.5 text-xs text-text-secondary">O saldo será calculado pelas movimentações confirmadas em uma etapa futura.</p>
</div>
