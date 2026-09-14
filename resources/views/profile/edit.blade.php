@extends('layouts.app')

@section('title', 'Meu perfil')
@section('header', 'Meu perfil')

@section('content')
    @php($profile = $user->member ?? $user)

    <header class="mb-8">
        <p class="ui-page-kicker">Conta</p>
        <h1 class="mt-1 ui-page-title">Meu perfil</h1>
        <p class="mt-2 ui-page-copy">Gerencie sua foto, informações pessoais e credenciais de acesso.</p>
    </header>

    <div class="grid max-w-5xl gap-6 lg:grid-cols-[minmax(0,1.45fr)_minmax(18rem,0.85fr)]">
        <section class="ui-card p-6 sm:p-8">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                @if ($user->profile_photo_path)
                    <img class="size-20 rounded-2xl border border-border-default object-cover" src="{{ asset('storage/'.$user->profile_photo_path) }}" alt="Foto de {{ $user->display_name }}">
                @else
                    <span class="grid size-20 place-items-center rounded-2xl bg-brand-primary-soft text-2xl font-semibold text-brand-primary" aria-hidden="true">{{ str($user->display_name)->substr(0, 1)->upper() }}</span>
                @endif
                <div>
                    <h2 class="text-lg font-semibold text-text-primary">Informações pessoais</h2>
                    <p class="mt-1 text-sm leading-6 text-text-secondary">{{ $user->member ? 'Estes dados também atualizam seu cadastro de membro.' : 'Complete os dados básicos da sua conta de administrador.' }}</p>
                </div>
            </div>

            <form class="mt-7 grid gap-5" method="POST" action="{{ route('profile.details.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PATCH')

                <div>
                    <label class="ui-label" for="profile_photo">Foto do perfil</label>
                    <input class="ui-input file:mr-3 file:rounded-lg file:border-0 file:bg-brand-primary-soft file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-brand-primary" id="profile_photo" name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp">
                    <p class="mt-1.5 text-xs text-text-secondary">JPG, PNG ou WebP, com até 2 MB.</p>
                    @error('profile_photo') <p class="mt-2 text-sm text-danger">{{ $message }}</p> @enderror
                    @if ($user->profile_photo_path)
                        <label class="mt-3 flex items-center gap-2 text-sm text-text-secondary"><input class="rounded border-border-default text-brand-primary focus:ring-brand-primary" name="remove_profile_photo" type="checkbox" value="1"> Remover foto atual</label>
                    @endif
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2"><label class="ui-label" for="name">Nome</label><input class="ui-input" id="name" name="name" value="{{ old('name', $profile->name ?? $user->display_name) }}" maxlength="255" required>@error('name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror</div>
                    <div><label class="ui-label" for="cpf">CPF{{ $user->member ? ' *' : '' }}</label><input class="ui-input" id="cpf" name="cpf" value="{{ old('cpf', $profile->cpf) }}" inputmode="numeric" maxlength="14" @required($user->member) data-cpf-mask>@error('cpf') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror</div>
                    <div><label class="ui-label" for="phone">Telefone</label><input class="ui-input" id="phone" name="phone" value="{{ old('phone', $profile->phone) }}" inputmode="tel" maxlength="15" data-phone-mask>@error('phone') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror</div>
                    <div class="sm:col-span-2"><label class="ui-label" for="email">E-mail</label><input class="ui-input" id="email" name="email" value="{{ old('email', $profile->email) }}" type="email" maxlength="255" autocomplete="email">@error('email') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror</div>
                </div>

                <div class="flex justify-end border-t border-border-default pt-5"><button class="ui-button-primary" type="submit">Salvar informações</button></div>
            </form>
        </section>

        <div class="grid content-start gap-6">
            <section class="ui-card p-6">
                <h2 class="text-lg font-semibold text-text-primary">Nome de usuário</h2>
                <p class="mt-1 text-sm leading-6 text-text-secondary">Use de 3 a 50 caracteres: letras sem acento, números, ponto, hífen ou sublinhado.</p>
                <form class="mt-5 grid gap-4" method="POST" action="{{ route('profile.username.update') }}">
                    @csrf
                    @method('PATCH')
                    <div><label class="ui-label" for="username">Usuário</label><input class="ui-input" id="username" name="username" value="{{ old('username', $user->username) }}" minlength="3" maxlength="50" required autocomplete="username">@error('username') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror</div>
                    <button class="ui-button-outline" type="submit">Atualizar usuário</button>
                </form>
            </section>

            <section class="ui-card p-6">
                <h2 class="text-lg font-semibold text-text-primary">Alterar senha</h2>
                <p class="mt-1 text-sm leading-6 text-text-secondary">Escolha uma senha com pelo menos 12 caracteres, letras maiúsculas, minúsculas e números.</p>
                <form class="mt-5 grid gap-4" method="POST" action="{{ route('profile.password.update') }}">
                    @csrf
                    @method('PUT')
                    <div><label class="ui-label" for="current_password">Senha atual</label><input class="ui-input" id="current_password" name="current_password" type="password" autocomplete="current-password" required>@error('current_password') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror</div>
                    <div><label class="ui-label" for="password">Nova senha</label><input class="ui-input" id="password" name="password" type="password" autocomplete="new-password" required>@error('password') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror</div>
                    <div><label class="ui-label" for="password_confirmation">Confirmar nova senha</label><input class="ui-input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
                    <button class="ui-button-outline" type="submit">Atualizar senha</button>
                </form>
            </section>
        </div>
    </div>
@endsection
