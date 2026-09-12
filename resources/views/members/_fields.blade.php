@php
    $member ??= null;
    $selectedState = old('state_id', $member?->city?->state_id);
    $selectedCity = old('city_id', $member?->city_id);
@endphp

<div class="grid gap-5 sm:grid-cols-2" data-dependent-cities data-cities-base-url="{{ url('/organization/states') }}" data-postal-code-lookup-url="{{ url('/organization/postal-codes') }}">
    <div class="sm:col-span-2">
        <label class="ui-label" for="name">Nome completo <span class="text-danger">*</span></label>
        <input class="ui-input" id="name" name="name" value="{{ old('name', $member?->name) }}" maxlength="255" required autocomplete="name">
        @error('name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="ui-label" for="cpf">CPF <span class="text-danger">*</span></label>
        <input class="ui-input" id="cpf" name="cpf" value="{{ old('cpf', $member?->cpf) }}" inputmode="numeric" maxlength="14" required data-cpf-mask>
        @error('cpf') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="ui-label" for="sex">Sexo</label>
        <select class="ui-select" id="sex" name="sex">
            <option value="">Não informado</option>
            @foreach ($sexes as $sex)
                <option value="{{ $sex->value }}" @selected(old('sex', $member?->sex?->value) === $sex->value)>{{ $sex->label() }}</option>
            @endforeach
        </select>
        @error('sex') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="ui-label" for="birth_date">Data de nascimento</label>
        <input class="ui-input" id="birth_date" name="birth_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('birth_date', $member?->birth_date?->toDateString()) }}">
        @error('birth_date') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="ui-label" for="phone">Telefone</label>
        <input class="ui-input" id="phone" name="phone" value="{{ old('phone', $member?->phone) }}" inputmode="tel" maxlength="15" autocomplete="tel" data-phone-mask>
        @error('phone') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
    </div>
    <div class="sm:col-span-2">
        <label class="ui-label" for="email">E-mail</label>
        <input class="ui-input" id="email" name="email" type="email" value="{{ old('email', $member?->email) }}" maxlength="255" autocomplete="email">
        @error('email') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2 border-t border-border-default pt-5">
        <h3 class="font-semibold text-text-primary">Endereço</h3>
        <p class="mt-1 text-sm text-text-secondary">Informe o CEP para preencher cidade, estado, bairro e logradouro automaticamente.</p>
    </div>
    <div>
        <label class="ui-label" for="postal_code">CEP</label>
        <input class="ui-input" id="postal_code" name="postal_code" value="{{ old('postal_code', $member?->postal_code) }}" inputmode="numeric" maxlength="9" autocomplete="postal-code" data-postal-code>
        <p class="mt-2 hidden text-sm text-text-secondary" data-postal-code-feedback role="status"></p>
        @error('postal_code') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="ui-label" for="state_id">Estado <span class="text-danger">*</span></label>
        <select class="ui-select" id="state_id" name="state_id" required data-state-select>
            <option value="">Selecione</option>
            @foreach ($states as $state)
                <option value="{{ $state->id }}" @selected((string) $selectedState === (string) $state->id)>{{ $state->name }} · {{ $state->abbreviation }}</option>
            @endforeach
        </select>
        @error('state_id') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="ui-label" for="city_id">Cidade <span class="text-danger">*</span></label>
        <select class="ui-select disabled:bg-border-default" id="city_id" name="city_id" required data-city-select @disabled(! $selectedState)>
            <option value="">{{ $selectedState ? 'Selecione' : 'Escolha um estado primeiro' }}</option>
            @foreach ($cities as $city)
                <option value="{{ $city->id }}" @selected((string) $selectedCity === (string) $city->id)>{{ $city->name }}</option>
            @endforeach
        </select>
        @error('city_id') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="ui-label" for="neighborhood">Bairro</label>
        <input class="ui-input" id="neighborhood" name="neighborhood" value="{{ old('neighborhood', $member?->neighborhood) }}" maxlength="255" data-neighborhood-input>
    </div>
    <div class="sm:col-span-2">
        <label class="ui-label" for="street">Logradouro</label>
        <input class="ui-input" id="street" name="street" value="{{ old('street', $member?->street) }}" maxlength="255" data-street-input>
    </div>
    <div>
        <label class="ui-label" for="number">Número</label>
        <input class="ui-input" id="number" name="number" value="{{ old('number', $member?->number) }}" maxlength="30">
    </div>
    <div>
        <label class="ui-label" for="complement">Complemento</label>
        <input class="ui-input" id="complement" name="complement" value="{{ old('complement', $member?->complement) }}" maxlength="255" data-complement-input>
    </div>
</div>
