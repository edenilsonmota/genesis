@if ($errors->any())
    <section class="ui-alert-error" role="alert">
        <p class="font-semibold">Revise os dados informados.</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </section>
@endif
