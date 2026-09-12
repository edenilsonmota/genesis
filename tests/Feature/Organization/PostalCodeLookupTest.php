<?php

use App\Models\Area;
use App\Models\City;
use App\Models\State;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function viaCepUrl(string $postalCode): string
{
    return rtrim((string) config('services.viacep.base_url'), '/')."/{$postalCode}/json/";
}

function viaCepAddress(array $overrides = []): array
{
    return array_replace([
        'cep' => '01310-100',
        'logradouro' => ' Avenida   Paulista ',
        'complemento' => 'lado par',
        'bairro' => 'Bela Vista',
        'localidade' => 'São Paulo',
        'uf' => 'SP',
        'ibge' => '3550308',
    ], $overrides);
}

it('renders the postal code lookup hook in the church form', function () {
    Area::factory()->create();
    $administrator = User::factory()->globalAdministrator()->create();

    $response = $this->actingAs($administrator)->get(route('organization.index', [
        'panel' => 'create-church',
    ]));

    $response
        ->assertSee('data-postal-code-lookup-url', false)
        ->assertSee('data-postal-code-feedback', false);
});

it('returns a normalized address linked to the local city and state', function () {
    $state = State::factory()->create([
        'ibge_code' => 35,
        'abbreviation' => 'SP',
        'name' => 'São Paulo',
    ]);
    $city = City::factory()->for($state)->create([
        'ibge_code' => 3550308,
        'name' => 'São Paulo',
    ]);
    $administrator = User::factory()->globalAdministrator()->create();
    Cache::forget('postal-code:01310100');
    Http::preventStrayRequests();
    Http::fake([
        viaCepUrl('01310100') => Http::response(viaCepAddress()),
    ]);

    $response = $this->actingAs($administrator)
        ->getJson(route('organization.postal-codes.show', '01310100'));

    $response->assertOk()->assertExactJson([
        'data' => [
            'postal_code' => '01310100',
            'street' => 'Avenida Paulista',
            'neighborhood' => 'Bela Vista',
            'complement' => 'lado par',
            'state' => [
                'id' => $state->id,
                'ibge_code' => 35,
                'abbreviation' => 'SP',
                'name' => 'São Paulo',
            ],
            'city' => [
                'id' => $city->id,
                'ibge_code' => 3550308,
                'name' => 'São Paulo',
            ],
        ],
    ]);
    Http::assertSent(fn (Request $request): bool => $request->url() === viaCepUrl('01310100'));
});

it('returns 404 when the postal code does not exist', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    Cache::forget('postal-code:99999999');
    Http::preventStrayRequests();
    Http::fake([
        viaCepUrl('99999999') => Http::response(['erro' => 'true']),
    ]);

    $response = $this->actingAs($administrator)
        ->getJson(route('organization.postal-codes.show', '99999999'));

    $response
        ->assertNotFound()
        ->assertJson(['message' => 'CEP não encontrado.']);
});

it('returns 422 when the IBGE city is absent from the local catalog', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    Cache::forget('postal-code:01001000');
    Http::preventStrayRequests();
    Http::fake([
        viaCepUrl('01001000') => Http::response(viaCepAddress()),
    ]);

    $response = $this->actingAs($administrator)
        ->getJson(route('organization.postal-codes.show', '01001000'));

    $response
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'A cidade deste CEP não está disponível no catálogo local. Atualize os dados do IBGE.',
        ]);
});

it('returns 422 without an external request for a malformed postal code', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    Http::preventStrayRequests();

    $response = $this->actingAs($administrator)
        ->getJson(route('organization.postal-codes.show', '01310'));

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('postal_code')
        ->assertJsonPath('errors.postal_code.0', 'Informe um CEP válido com 8 dígitos.');
    Http::assertNothingSent();
});

it('returns 503 when the postal code provider sends an invalid response', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    Cache::forget('postal-code:20040002');
    Http::preventStrayRequests();
    Http::fake([
        viaCepUrl('20040002') => Http::response([]),
    ]);

    $response = $this->actingAs($administrator)
        ->getJson(route('organization.postal-codes.show', '20040002'));

    $response
        ->assertServiceUnavailable()
        ->assertJson([
            'message' => 'Não foi possível consultar o CEP agora. Tente novamente.',
        ]);
});
