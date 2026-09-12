<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PostalCodeLookupService
{
    /**
     * @return array{ibge_code: int, street: string, neighborhood: string, complement: string}|null
     */
    public function lookup(string $postalCode): ?array
    {
        return Cache::remember(
            "postal-code:{$postalCode}",
            now()->addDays(7),
            fn (): ?array => $this->fetch($postalCode),
        );
    }

    /**
     * @return array{ibge_code: int, street: string, neighborhood: string, complement: string}|null
     */
    private function fetch(string $postalCode): ?array
    {
        $baseUrl = rtrim((string) config('services.viacep.base_url'), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('O serviço de consulta de CEP não está configurado.');
        }

        $payload = Http::acceptJson()
            ->connectTimeout(2)
            ->timeout(5)
            ->retry([200, 500], 0, function (Throwable $exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && ($exception->response->serverError() || $exception->response->tooManyRequests()));
            })
            ->get("{$baseUrl}/{$postalCode}/json/")
            ->throw()
            ->json();

        if (! is_array($payload)) {
            throw new RuntimeException('O serviço de consulta de CEP retornou uma resposta inválida.');
        }

        if (($payload['erro'] ?? false) === true || ($payload['erro'] ?? null) === 'true') {
            return null;
        }

        $ibgeCode = $payload['ibge'] ?? null;

        if (! is_string($ibgeCode) || ! ctype_digit($ibgeCode)) {
            throw new RuntimeException('O serviço de consulta de CEP não informou o código IBGE da cidade.');
        }

        return [
            'ibge_code' => (int) $ibgeCode,
            'street' => $this->normalize($payload['logradouro'] ?? null),
            'neighborhood' => $this->normalize($payload['bairro'] ?? null),
            'complement' => $this->normalize($payload['complemento'] ?? null),
        ];
    }

    private function normalize(mixed $value): string
    {
        return is_string($value) ? Str::squish($value) : '';
    }
}
