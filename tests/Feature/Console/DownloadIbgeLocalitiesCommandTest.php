<?php

use App\Services\IbgeLocalitiesService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

function ibgeMunicipality(int $code, string $name, int $stateCode): array
{
    return [
        'id' => $code,
        'nome' => $name,
        'microrregiao' => [
            'mesorregiao' => [
                'UF' => ['id' => $stateCode],
            ],
        ],
    ];
}

it('downloads and normalizes an IBGE snapshot with source metadata', function () {
    $output = 'storage/framework/testing/ibge-localities.json';
    File::delete(base_path($output));
    $this->travelTo(CarbonImmutable::parse('2026-09-11T15:30:00+00:00'));
    Http::preventStrayRequests();
    Http::fake([
        IbgeLocalitiesService::STATES_URL => Http::response([
            ['id' => 35, 'sigla' => ' sp ', 'nome' => ' São   Paulo '],
            ['id' => 12, 'sigla' => 'AC', 'nome' => 'Acre'],
        ]),
        IbgeLocalitiesService::CITIES_URL => Http::response([
            ibgeMunicipality(3550308, ' São   Paulo ', 35),
            ibgeMunicipality(1200401, 'Rio Branco', 12),
        ]),
    ]);

    try {
        $this->artisan('ibge:download-localities', ['--output' => $output])
            ->expectsOutputToContain('Estados')
            ->expectsOutputToContain('Municípios')
            ->assertSuccessful();

        $snapshot = json_decode(File::get(base_path($output)), true, flags: JSON_THROW_ON_ERROR);

        expect($snapshot['metadata'])
            ->toMatchArray([
                'snapshot_version' => 1,
                'retrieved_at' => '2026-09-11T15:30:00+00:00',
                'source' => [
                    'name' => 'IBGE API de Localidades',
                    'documentation_url' => IbgeLocalitiesService::DOCUMENTATION_URL,
                    'states_url' => IbgeLocalitiesService::STATES_URL,
                    'cities_url' => IbgeLocalitiesService::CITIES_URL,
                ],
                'counts' => ['states' => 2, 'cities' => 2],
            ])
            ->and($snapshot['states'])->toBe([
                ['ibge_code' => 12, 'abbreviation' => 'AC', 'name' => 'Acre'],
                ['ibge_code' => 35, 'abbreviation' => 'SP', 'name' => 'São Paulo'],
            ])
            ->and($snapshot['cities'])->toBe([
                ['ibge_code' => 1200401, 'state_ibge_code' => 12, 'name' => 'Rio Branco'],
                ['ibge_code' => 3550308, 'state_ibge_code' => 35, 'name' => 'São Paulo'],
            ]);
        Http::assertSent(fn (Request $request): bool => $request->url() === IbgeLocalitiesService::STATES_URL);
        Http::assertSent(fn (Request $request): bool => $request->url() === IbgeLocalitiesService::CITIES_URL);
    } finally {
        File::delete(base_path($output));
    }
});

it('does not replace the snapshot when the IBGE payload is inconsistent', function () {
    $output = 'storage/framework/testing/ibge-localities-invalid.json';
    $path = base_path($output);
    File::ensureDirectoryExists(dirname($path));
    File::put($path, "snapshot anterior\n");
    Http::preventStrayRequests();
    Http::fake([
        IbgeLocalitiesService::STATES_URL => Http::response([
            ['id' => 35, 'sigla' => 'SP', 'nome' => 'São Paulo'],
        ]),
        IbgeLocalitiesService::CITIES_URL => Http::response([
            ibgeMunicipality(1200401, 'Rio Branco', 12),
        ]),
    ]);

    try {
        $this->artisan('ibge:download-localities', ['--output' => $output])
            ->expectsOutputToContain('referencia a UF inexistente 12')
            ->assertFailed();

        expect(File::get($path))->toBe("snapshot anterior\n");
    } finally {
        File::delete($path);
    }
});
