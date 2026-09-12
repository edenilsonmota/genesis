<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\City;
use App\Services\PostalCodeLookupService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class PostalCodeController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(string $postalCode, PostalCodeLookupService $lookup): JsonResponse
    {
        Gate::authorize('viewAny', Church::class);

        Validator::make(
            ['postal_code' => $postalCode],
            ['postal_code' => ['required', 'digits:8']],
            ['postal_code.digits' => 'Informe um CEP válido com 8 dígitos.'],
        )->validate();

        try {
            $address = $lookup->lookup($postalCode);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Não foi possível consultar o CEP agora. Tente novamente.',
            ], 503);
        }

        if ($address === null) {
            return response()->json(['message' => 'CEP não encontrado.'], 404);
        }

        $city = City::query()
            ->with('state')
            ->where('ibge_code', $address['ibge_code'])
            ->first();

        if ($city === null) {
            return response()->json([
                'message' => 'A cidade deste CEP não está disponível no catálogo local. Atualize os dados do IBGE.',
            ], 422);
        }

        return response()->json([
            'data' => [
                'postal_code' => $postalCode,
                'street' => $address['street'],
                'neighborhood' => $address['neighborhood'],
                'complement' => $address['complement'],
                'state' => [
                    'id' => $city->state->id,
                    'ibge_code' => $city->state->ibge_code,
                    'abbreviation' => $city->state->abbreviation,
                    'name' => $city->state->name,
                ],
                'city' => [
                    'id' => $city->id,
                    'ibge_code' => $city->ibge_code,
                    'name' => $city->name,
                ],
            ],
        ]);
    }
}
