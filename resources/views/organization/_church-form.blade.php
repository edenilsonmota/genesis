@php
    $selectedStateId = old('state_id', $church?->city?->state_id);
    $selectedCityId = old('city_id', $church?->city_id);
@endphp

<form class="grid gap-5" method="POST" action="{{ $action }}" data-dependent-cities data-cities-base-url="{{ url('/organization/states') }}" data-postal-code-lookup-url="{{ url('/organization/postal-codes') }}">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div>
        <label class="mb-2 block text-sm font-semibold text-slate-700" for="church_name">Nome</label>
        <input class="block w-full rounded-xl border border-stone-300 px-3.5 py-3 text-sm shadow-sm" id="church_name" name="name" value="{{ old('name', $church?->name) }}" maxlength="255" required>
        @error('name') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label class="mb-2 block text-sm font-semibold text-slate-700" for="church_postal_code">CEP</label>
            <input class="block w-full rounded-xl border border-stone-300 px-3.5 py-3 text-sm shadow-sm" id="church_postal_code" name="postal_code" value="{{ old('postal_code', $church?->postal_code) }}" inputmode="numeric" maxlength="9" placeholder="00000-000" aria-describedby="church_postal_code_feedback" autocomplete="postal-code" data-postal-code required>
            <p class="mt-2 hidden text-sm" id="church_postal_code_feedback" aria-live="polite" data-postal-code-feedback></p>
            @error('postal_code') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-2 block text-sm font-semibold text-slate-700" for="church_status">Status</label>
            <select class="block w-full rounded-xl border border-stone-300 bg-white px-3.5 py-3 text-sm shadow-sm" id="church_status" name="status" required>
                <option value="active" @selected(old('status', $church?->status?->value ?? 'active') === 'active')>Ativa</option>
                <option value="inactive" @selected(old('status', $church?->status?->value) === 'inactive')>Inativa</option>
            </select>
            @error('status') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label class="mb-2 block text-sm font-semibold text-slate-700" for="church_state">Estado</label>
            <select class="block w-full rounded-xl border border-stone-300 bg-white px-3.5 py-3 text-sm shadow-sm" id="church_state" name="state_id" data-state-select required>
                <option value="">Selecione</option>
                @foreach ($states as $state)
                    <option value="{{ $state->id }}" @selected((string) $selectedStateId === (string) $state->id)>{{ $state->name }} ({{ $state->abbreviation }})</option>
                @endforeach
            </select>
            @error('state_id') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-2 block text-sm font-semibold text-slate-700" for="church_city">Cidade</label>
            <select class="block w-full rounded-xl border border-stone-300 bg-white px-3.5 py-3 text-sm shadow-sm disabled:bg-stone-100" id="church_city" name="city_id" data-city-select required @disabled(! $selectedStateId)>
                <option value="">{{ $selectedStateId ? 'Selecione' : 'Escolha um estado primeiro' }}</option>
                @foreach ($formCities as $city)
                    <option value="{{ $city->id }}" @selected((string) $selectedCityId === (string) $city->id)>{{ $city->name }}</option>
                @endforeach
            </select>
            @error('city_id') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    @if ($states->isEmpty())
        <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">O catálogo de estados e cidades ainda não foi carregado.</p>
    @endif

    <div class="grid gap-5 sm:grid-cols-[1fr_8rem]">
        <div>
            <label class="mb-2 block text-sm font-semibold text-slate-700" for="church_street">Logradouro</label>
            <input class="block w-full rounded-xl border border-stone-300 px-3.5 py-3 text-sm shadow-sm" id="church_street" name="street" value="{{ old('street', $church?->street) }}" maxlength="255" autocomplete="address-line1" data-street-input required>
            @error('street') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-2 block text-sm font-semibold text-slate-700" for="church_number">Número</label>
            <input class="block w-full rounded-xl border border-stone-300 px-3.5 py-3 text-sm shadow-sm" id="church_number" name="number" value="{{ old('number', $church?->number) }}" maxlength="30" placeholder="S/N" required>
            @error('number') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label class="mb-2 block text-sm font-semibold text-slate-700" for="church_neighborhood">Bairro</label>
            <input class="block w-full rounded-xl border border-stone-300 px-3.5 py-3 text-sm shadow-sm" id="church_neighborhood" name="neighborhood" value="{{ old('neighborhood', $church?->neighborhood) }}" maxlength="255" data-neighborhood-input required>
            @error('neighborhood') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-2 block text-sm font-semibold text-slate-700" for="church_complement">Complemento <span class="font-normal text-slate-400">(opcional)</span></label>
            <input class="block w-full rounded-xl border border-stone-300 px-3.5 py-3 text-sm shadow-sm" id="church_complement" name="complement" value="{{ old('complement', $church?->complement) }}" maxlength="255" autocomplete="address-line2" data-complement-input>
            @error('complement') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="flex flex-col-reverse gap-3 border-t border-stone-200 pt-5 sm:flex-row sm:justify-end">
        <a class="rounded-xl border border-stone-300 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-stone-50" href="{{ route('organization.index') }}">Cancelar</a>
        <button class="rounded-xl bg-genesis-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-genesis-700 disabled:cursor-not-allowed disabled:opacity-50" type="submit" @disabled($states->isEmpty())>{{ $submitLabel }}</button>
    </div>
</form>
