<div>
    <label class="ui-label" for="area_name">Nome</label>
    <input class="ui-input px-3.5 py-3" id="area_name" name="name" value="{{ old('name', $area?->name) }}" maxlength="255" required autofocus>
    @error('name') <p class="mt-2 text-sm text-danger">{{ $message }}</p> @enderror
</div>
<div>
    <label class="ui-label" for="area_description">Descrição <span class="font-normal text-text-disabled">(opcional)</span></label>
    <textarea class="ui-input min-h-28 resize-y px-3.5 py-3" id="area_description" name="description" maxlength="255">{{ old('description', $area?->description) }}</textarea>
    @error('description') <p class="mt-2 text-sm text-danger">{{ $message }}</p> @enderror
</div>
<div>
    <label class="ui-label" for="area_status">Status</label>
    <select class="ui-select px-3.5 py-3" id="area_status" name="status" required>
        <option value="active" @selected(old('status', $area?->status?->value ?? 'active') === 'active')>Ativa</option>
        <option value="inactive" @selected(old('status', $area?->status?->value) === 'inactive')>Inativa</option>
    </select>
    @error('status') <p class="mt-2 text-sm text-danger">{{ $message }}</p> @enderror
</div>
