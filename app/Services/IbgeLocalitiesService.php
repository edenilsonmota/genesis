<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

class IbgeLocalitiesService
{
    public const CITIES_URL = 'https://servicodados.ibge.gov.br/api/v1/localidades/municipios?orderBy=nome';

    public const DOCUMENTATION_URL = 'https://servicodados.ibge.gov.br/api/docs/localidades';

    public const SNAPSHOT_PATH = 'database/data/ibge-localities.json';

    public const STATES_URL = 'https://servicodados.ibge.gov.br/api/v1/localidades/estados?orderBy=nome';

    /**
     * @return array{
     *     metadata: array<string, mixed>,
     *     states: list<array{ibge_code: int, abbreviation: string, name: string}>,
     *     cities: list<array{ibge_code: int, state_ibge_code: int, name: string}>
     * }
     */
    public function download(): array
    {
        $states = $this->fetch(self::STATES_URL);
        $cities = $this->fetch(self::CITIES_URL);

        return $this->normalize($states, $cities);
    }

    /**
     * @return array{
     *     metadata: array<string, mixed>,
     *     states: list<array{ibge_code: int, abbreviation: string, name: string}>,
     *     cities: list<array{ibge_code: int, state_ibge_code: int, name: string}>
     * }
     */
    public function load(?string $path = null): array
    {
        $path ??= base_path(self::SNAPSHOT_PATH);

        if (! File::isFile($path)) {
            throw new RuntimeException("Snapshot de localidades não encontrado em {$path}.");
        }

        try {
            $snapshot = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('O snapshot de localidades contém JSON inválido.', previous: $exception);
        }

        if (! is_array($snapshot)) {
            throw new RuntimeException('O snapshot de localidades deve conter um objeto JSON.');
        }

        return $this->validateSnapshot($snapshot);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function save(array $snapshot, string $relativePath = self::SNAPSHOT_PATH): string
    {
        $snapshot = $this->validateSnapshot($snapshot);
        $path = $this->projectPath($relativePath);

        File::ensureDirectoryExists(dirname($path));

        try {
            $json = json_encode(
                $snapshot,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Não foi possível serializar o snapshot de localidades.', previous: $exception);
        }

        File::replace($path, $json.PHP_EOL, 0644);

        return $path;
    }

    /**
     * @return list<mixed>
     */
    private function fetch(string $url): array
    {
        $payload = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(120)
            ->retry([250, 1_000], 0, function (Throwable $exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && ($exception->response->serverError() || $exception->response->tooManyRequests()));
            })
            ->get($url)
            ->throw()
            ->json();

        if (! is_array($payload) || ! array_is_list($payload)) {
            throw new RuntimeException("A API do IBGE retornou um formato inesperado para {$url}.");
        }

        return $payload;
    }

    /**
     * @param  list<mixed>  $rawStates
     * @param  list<mixed>  $rawCities
     * @return array{
     *     metadata: array<string, mixed>,
     *     states: list<array{ibge_code: int, abbreviation: string, name: string}>,
     *     cities: list<array{ibge_code: int, state_ibge_code: int, name: string}>
     * }
     */
    private function normalize(array $rawStates, array $rawCities): array
    {
        $states = collect($rawStates)
            ->map(function (mixed $state): array {
                if (! is_array($state)) {
                    throw new RuntimeException('A API do IBGE retornou um estado inválido.');
                }

                return [
                    'ibge_code' => $this->positiveInteger($state['id'] ?? null, 'estado'),
                    'abbreviation' => $this->abbreviation($state['sigla'] ?? null),
                    'name' => $this->name($state['nome'] ?? null, 'estado'),
                ];
            })
            ->sortBy('ibge_code')
            ->values()
            ->all();

        $this->ensureUnique($states, 'ibge_code', 'estados');
        $this->ensureUnique($states, 'abbreviation', 'estados');

        $stateCodes = array_fill_keys(array_column($states, 'ibge_code'), true);

        $cities = collect($rawCities)
            ->map(function (mixed $city) use ($stateCodes): array {
                if (! is_array($city)) {
                    throw new RuntimeException('A API do IBGE retornou um município inválido.');
                }

                $stateCode = data_get($city, 'microrregiao.mesorregiao.UF.id')
                    ?? data_get($city, 'regiao-imediata.regiao-intermediaria.UF.id');
                $stateCode = $this->positiveInteger($stateCode, 'UF do município');

                if (! isset($stateCodes[$stateCode])) {
                    throw new RuntimeException("O município informado pelo IBGE referencia a UF inexistente {$stateCode}.");
                }

                return [
                    'ibge_code' => $this->positiveInteger($city['id'] ?? null, 'município'),
                    'state_ibge_code' => $stateCode,
                    'name' => $this->name($city['nome'] ?? null, 'município'),
                ];
            })
            ->sortBy('ibge_code')
            ->values()
            ->all();

        $this->ensureUnique($cities, 'ibge_code', 'municípios');

        if ($states === [] || $cities === []) {
            throw new RuntimeException('A API do IBGE retornou um catálogo de localidades vazio.');
        }

        return [
            'metadata' => [
                'snapshot_version' => 1,
                'retrieved_at' => now()->utc()->toIso8601String(),
                'source' => [
                    'name' => 'IBGE API de Localidades',
                    'documentation_url' => self::DOCUMENTATION_URL,
                    'states_url' => self::STATES_URL,
                    'cities_url' => self::CITIES_URL,
                ],
                'counts' => [
                    'states' => count($states),
                    'cities' => count($cities),
                ],
            ],
            'states' => $states,
            'cities' => $cities,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{
     *     metadata: array<string, mixed>,
     *     states: list<array{ibge_code: int, abbreviation: string, name: string}>,
     *     cities: list<array{ibge_code: int, state_ibge_code: int, name: string}>
     * }
     */
    private function validateSnapshot(array $snapshot): array
    {
        $metadata = $snapshot['metadata'] ?? null;
        $states = $snapshot['states'] ?? null;
        $cities = $snapshot['cities'] ?? null;

        if (! is_array($metadata) || ! is_array($states) || ! array_is_list($states)
            || ! is_array($cities) || ! array_is_list($cities)) {
            throw new RuntimeException('O snapshot de localidades possui uma estrutura inválida.');
        }

        if (($metadata['snapshot_version'] ?? null) !== 1
            || data_get($metadata, 'source.documentation_url') !== self::DOCUMENTATION_URL
            || data_get($metadata, 'source.states_url') !== self::STATES_URL
            || data_get($metadata, 'source.cities_url') !== self::CITIES_URL) {
            throw new RuntimeException('Os metadados da fonte do snapshot de localidades são inválidos.');
        }

        $retrievedAt = $metadata['retrieved_at'] ?? null;

        if (! is_string($retrievedAt) || ! CarbonImmutable::hasFormat($retrievedAt, 'Y-m-d\TH:i:sP')) {
            throw new RuntimeException('A data de obtenção do snapshot de localidades é inválida.');
        }

        foreach ($states as $state) {
            if (! is_array($state)
                || ! is_int($state['ibge_code'] ?? null)
                || ! is_string($state['abbreviation'] ?? null)
                || ! is_string($state['name'] ?? null)) {
                throw new RuntimeException('O snapshot contém um estado inválido.');
            }
        }

        foreach ($cities as $city) {
            if (! is_array($city)
                || ! is_int($city['ibge_code'] ?? null)
                || ! is_int($city['state_ibge_code'] ?? null)
                || ! is_string($city['name'] ?? null)) {
                throw new RuntimeException('O snapshot contém um município inválido.');
            }
        }

        $this->ensureUnique($states, 'ibge_code', 'estados');
        $this->ensureUnique($states, 'abbreviation', 'estados');
        $this->ensureUnique($cities, 'ibge_code', 'municípios');

        $stateCodes = array_fill_keys(array_column($states, 'ibge_code'), true);

        foreach ($cities as $city) {
            if (! isset($stateCodes[$city['state_ibge_code']])) {
                throw new RuntimeException("O município {$city['ibge_code']} referencia uma UF ausente do snapshot.");
            }
        }

        if (($metadata['counts']['states'] ?? null) !== count($states)
            || ($metadata['counts']['cities'] ?? null) !== count($cities)) {
            throw new RuntimeException('As contagens declaradas no snapshot de localidades são inválidas.');
        }

        /** @var array{metadata: array<string, mixed>, states: list<array{ibge_code: int, abbreviation: string, name: string}>, cities: list<array{ibge_code: int, state_ibge_code: int, name: string}>} $snapshot */
        return $snapshot;
    }

    private function positiveInteger(mixed $value, string $subject): int
    {
        if (! is_int($value) || $value <= 0) {
            throw new RuntimeException("A API do IBGE retornou um código inválido para {$subject}.");
        }

        return $value;
    }

    private function abbreviation(mixed $value): string
    {
        if (! is_string($value)) {
            throw new RuntimeException('A API do IBGE retornou uma sigla de estado inválida.');
        }

        $abbreviation = Str::upper(Str::squish($value));

        if (Str::length($abbreviation) !== 2) {
            throw new RuntimeException('A API do IBGE retornou uma sigla de estado inválida.');
        }

        return $abbreviation;
    }

    private function name(mixed $value, string $subject): string
    {
        if (! is_string($value) || ($name = Str::squish($value)) === '') {
            throw new RuntimeException("A API do IBGE retornou um nome inválido para {$subject}.");
        }

        return $name;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function ensureUnique(array $records, string $key, string $subject): void
    {
        if (count(array_unique(array_column($records, $key), SORT_REGULAR)) !== count($records)) {
            throw new RuntimeException("A fonte contém códigos ou identificadores duplicados para {$subject}.");
        }
    }

    private function projectPath(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));

        if ($relativePath === '' || str_starts_with($relativePath, '/')
            || preg_match('#(^|/)\.\.(/|$)#', $relativePath) === 1) {
            throw new RuntimeException('O caminho do snapshot deve ser relativo à raiz do projeto.');
        }

        return base_path($relativePath);
    }
}
