@if ($errors->any())
    <div class="ui-alert-error" role="alert"><p class="font-semibold">Revise os campos informados.</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<div><label class="ui-label" for="name">Nome</label><input class="ui-input" id="name" name="name" value="{{ old('name', $department->name ?? '') }}" maxlength="255" required autofocus data-uppercase-input></div>
<div><label class="ui-label" for="description">Descrição</label><textarea class="ui-input min-h-24" id="description" name="description" maxlength="255">{{ old('description', $department->description ?? '') }}</textarea></div>
