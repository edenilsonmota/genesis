@php
    $selectedScope = old('scope_type', $calendarEvent->exists ? ($calendarEvent->church_id ? 'church' : 'area') : ($currentChurch ? 'church' : 'area'));
    $selectedChurchId = old('church_id', $calendarEvent->church_id ?? $currentChurch?->id ?? request('church_id'));
@endphp

@if ($errors->any())
    <div class="ui-alert-error sm:col-span-2" role="alert"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<section class="grid gap-5 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label class="ui-label" for="title">Título</label>
        <input class="ui-input" id="title" name="title" value="{{ old('title', $calendarEvent->title) }}" maxlength="180" placeholder="Ex.: Cruzada Evangelística 2027" required autofocus>
    </div>
    <div>
        <div class="flex items-center justify-between gap-3"><label class="ui-label" for="calendar_event_type_id">Tipo</label><button class="mb-1.5 text-xs font-semibold text-brand-primary hover:text-brand-primary-hover" type="button" data-quick-event-type-trigger>Adicionar tipo</button></div>
        <select class="ui-select" id="calendar_event_type_id" name="calendar_event_type_id" data-event-type required>
            @foreach ($types as $type)<option value="{{ $type->id }}" @selected(old('calendar_event_type_id', $calendarEvent->calendar_event_type_id) === $type->id)>{{ $type->name }}</option>@endforeach
        </select>
    </div>
    <div>
        <label class="ui-label" for="status">Status</label>
        <select class="ui-select" id="status" name="status" required>
            @foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(old('status', $calendarEvent->status?->value) === $status->value)>{{ $status->label() }}</option>@endforeach
        </select>
    </div>
</section>

<section class="grid gap-5 border-t border-border-default pt-6 sm:grid-cols-2">
    <div class="flex items-center justify-between gap-4 rounded-2xl border border-border-default bg-surface-muted/50 p-4 sm:col-span-2">
        <div><p class="font-semibold text-text-primary">Evento de dia inteiro</p><p class="mt-1 text-xs text-text-secondary">Use para eventos sem um horário específico ou que durem vários dias.</p></div>
        <input type="hidden" name="all_day" value="0">
        <input class="size-5 rounded border-border-default text-brand-primary focus:ring-brand-cyan" id="all_day" name="all_day" type="checkbox" value="1" data-event-all-day @checked(old('all_day', $calendarEvent->all_day))>
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
        <div><label class="ui-label" for="start_date">Data inicial</label><input class="ui-input" id="start_date" name="start_date" type="date" value="{{ old('start_date', $startDate) }}" required></div>
        <div data-event-time><label class="ui-label" for="start_time">Hora inicial</label><input class="ui-input" id="start_time" name="start_time" type="text" inputmode="numeric" autocomplete="off" value="{{ old('start_time', $startTime) }}" placeholder="19:00" required data-time-24><p class="mt-1.5 text-xs text-text-secondary">Formato 24 horas — ex.: 19:00.</p></div>
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
        <div><label class="ui-label" for="end_date">Data final</label><input class="ui-input" id="end_date" name="end_date" type="date" value="{{ old('end_date', $endDate) }}" required></div>
        <div data-event-time><label class="ui-label" for="end_time">Hora final</label><input class="ui-input" id="end_time" name="end_time" type="text" inputmode="numeric" autocomplete="off" value="{{ old('end_time', $endTime) }}" placeholder="21:00" required data-time-24><p class="mt-1.5 text-xs text-text-secondary">Formato 24 horas — ex.: 21:00.</p></div>
    </div>
</section>

<section class="grid gap-5 border-t border-border-default pt-6 sm:grid-cols-2">
    @if (auth()->user()->isGlobalAdministrator())
        <div>
            <label class="ui-label" for="scope_type">Escopo</label>
            <select class="ui-select" id="scope_type" name="scope_type" data-event-scope-type required>
                <option value="area" @selected($selectedScope === 'area')>Toda a área · {{ $area->name }}</option>
                <option value="church" @selected($selectedScope === 'church')>Uma igreja</option>
            </select>
        </div>
        <div data-event-church-field>
            <label class="ui-label" for="church_id">Igreja</label>
            <select class="ui-select" id="church_id" name="church_id" data-event-church>
                <option value="">Selecione a igreja</option>
                @foreach ($churches as $church)<option value="{{ $church->id }}" @selected($selectedChurchId === $church->id)>{{ $church->name }}</option>@endforeach
            </select>
        </div>
    @else
        <input type="hidden" name="scope_type" value="church">
        <input type="hidden" name="church_id" value="{{ $currentChurch->id }}">
        <div class="rounded-2xl border border-border-default bg-surface-muted/50 p-4 sm:col-span-2">
            <p class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Igreja</p>
            <p class="mt-1 font-semibold text-text-primary">{{ $currentChurch->name }}</p>
        </div>
    @endif

    <div>
        <label class="ui-label" for="department_id">Departamento</label>
        <select class="ui-select" id="department_id" name="department_id" data-event-department>
            <option value="">Sem departamento</option>
            @foreach ($departments as $department)<option value="{{ $department->id }}" @selected(old('department_id', $calendarEvent->department_id) === $department->id)>{{ $department->name }}</option>@endforeach
        </select>
    </div>
    <div>
        <label class="ui-label" for="responsible_member_id">Responsável</label>
        <select class="ui-select" id="responsible_member_id" name="responsible_member_id" data-event-responsible>
            <option value="">Sem responsável</option>
            @foreach ($members as $member)
                <option value="{{ $member->id }}" data-church-ids="{{ $member->memberships->pluck('church_id')->join(',') }}" @selected(old('responsible_member_id', $calendarEvent->responsible_member_id) === $member->id)>{{ $member->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="ui-label" for="visibility">Visibilidade <x-tooltip text="Igreja: usuários autorizados do escopo. Departamento: integrantes do departamento e gestores. Privado: criador, responsável e gestores." /></label>
        <select class="ui-select" id="visibility" name="visibility" data-event-visibility required>
            @foreach ($visibilities as $visibility)<option value="{{ $visibility->value }}" @selected(old('visibility', $calendarEvent->visibility?->value) === $visibility->value)>{{ $visibility->label() }}</option>@endforeach
        </select>
    </div>
    <div>
        <label class="ui-label" for="location">Local</label>
        <input class="ui-input" id="location" name="location" value="{{ old('location', $calendarEvent->location) }}" maxlength="255" placeholder="Ex.: Templo sede">
    </div>
</section>

<section class="grid gap-5 border-t border-border-default pt-6 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label class="ui-label" for="description">Descrição</label>
        <textarea class="ui-input min-h-32" id="description" name="description" maxlength="5000" placeholder="Informações adicionais do evento">{{ old('description', $calendarEvent->description) }}</textarea>
    </div>
    <label class="flex items-start gap-3 rounded-2xl border border-border-default p-4">
        <input type="hidden" name="creator_can_edit" value="0"><input class="mt-0.5 size-4 rounded border-border-default text-brand-primary focus:ring-brand-cyan" name="creator_can_edit" type="checkbox" value="1" @checked(old('creator_can_edit', $calendarEvent->creator_can_edit ?? true))>
        <span><span class="block text-sm font-semibold text-text-primary">Criador pode editar</span><span class="mt-1 block text-xs text-text-secondary">Enquanto continuar com acesso ao escopo.</span></span>
    </label>
    <label class="flex items-start gap-3 rounded-2xl border border-border-default p-4">
        <input type="hidden" name="responsible_can_edit" value="0"><input class="mt-0.5 size-4 rounded border-border-default text-brand-primary focus:ring-brand-cyan" name="responsible_can_edit" type="checkbox" value="1" @checked(old('responsible_can_edit', $calendarEvent->responsible_can_edit ?? true))>
        <span><span class="block text-sm font-semibold text-text-primary">Responsável pode editar</span><span class="mt-1 block text-xs text-text-secondary">Gestores com escrita sempre podem administrar.</span></span>
    </label>
</section>
