<div>
    <label class="mb-2 block text-sm font-semibold text-slate-700" for="area_name">Nome</label>
    <input class="block w-full rounded-xl border border-stone-300 px-3.5 py-3 text-sm shadow-sm" id="area_name" name="name" value="{{ old('name', $area?->name) }}" maxlength="255" required autofocus>
    @error('name') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
</div>
<div>
    <label class="mb-2 block text-sm font-semibold text-slate-700" for="area_description">Descrição <span class="font-normal text-slate-400">(opcional)</span></label>
    <textarea class="block min-h-28 w-full resize-y rounded-xl border border-stone-300 px-3.5 py-3 text-sm shadow-sm" id="area_description" name="description" maxlength="255">{{ old('description', $area?->description) }}</textarea>
    @error('description') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
</div>
<div>
    <label class="mb-2 block text-sm font-semibold text-slate-700" for="area_status">Status</label>
    <select class="block w-full rounded-xl border border-stone-300 bg-white px-3.5 py-3 text-sm shadow-sm" id="area_status" name="status" required>
        <option value="active" @selected(old('status', $area?->status?->value ?? 'active') === 'active')>Ativa</option>
        <option value="inactive" @selected(old('status', $area?->status?->value) === 'inactive')>Inativa</option>
    </select>
    @error('status') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
</div>
