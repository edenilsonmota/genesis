<?php

namespace Database\Seeders;

use App\Services\IbgeLocalitiesService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IbgeLocalitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(IbgeLocalitiesService $localities): void
    {
        $snapshot = $localities->load();

        DB::transaction(function () use ($snapshot): void {
            $this->backfillLegacyStates($snapshot['states']);

            DB::table('states')->upsert(
                $snapshot['states'],
                ['ibge_code'],
                ['abbreviation', 'name'],
            );

            $stateIds = DB::table('states')
                ->whereIn('ibge_code', array_column($snapshot['states'], 'ibge_code'))
                ->pluck('id', 'ibge_code');

            if ($stateIds->count() !== count($snapshot['states'])) {
                throw new RuntimeException('Nem todos os estados do snapshot foram persistidos.');
            }

            $cities = array_map(function (array $city) use ($stateIds): array {
                return [
                    'ibge_code' => $city['ibge_code'],
                    'state_id' => $stateIds->get($city['state_ibge_code']),
                    'name' => $city['name'],
                ];
            }, $snapshot['cities']);

            $this->backfillLegacyCities($cities);

            foreach (array_chunk($cities, 1_000) as $chunk) {
                DB::table('cities')->upsert(
                    $chunk,
                    ['ibge_code'],
                    ['state_id', 'name'],
                );
            }
        });
    }

    /**
     * @param  list<array{ibge_code: int, abbreviation: string, name: string}>  $states
     */
    private function backfillLegacyStates(array $states): void
    {
        foreach ($states as $state) {
            DB::table('states')
                ->whereNull('ibge_code')
                ->where('abbreviation', $state['abbreviation'])
                ->update([
                    'ibge_code' => $state['ibge_code'],
                    'name' => $state['name'],
                ]);
        }
    }

    /**
     * @param  list<array{ibge_code: int, state_id: int, name: string}>  $cities
     */
    private function backfillLegacyCities(array $cities): void
    {
        $sourceByLocation = collect($cities)->keyBy(
            fn (array $city): string => $city['state_id']."\0".$city['name'],
        );
        $claimedCodes = DB::table('cities')
            ->whereNotNull('ibge_code')
            ->pluck('ibge_code')
            ->mapWithKeys(fn (int $code): array => [$code => true]);
        $backfills = [];

        DB::table('cities')
            ->whereNull('ibge_code')
            ->orderBy('id')
            ->get(['id', 'state_id', 'name'])
            ->each(function (object $city) use ($sourceByLocation, $claimedCodes, &$backfills): void {
                $source = $sourceByLocation->get($city->state_id."\0".$city->name);

                if ($source === null || $claimedCodes->has($source['ibge_code'])) {
                    return;
                }

                $backfills[] = [
                    'id' => $city->id,
                    'ibge_code' => $source['ibge_code'],
                    'state_id' => $source['state_id'],
                    'name' => $source['name'],
                ];
                $claimedCodes->put($source['ibge_code'], true);
            });

        foreach (array_chunk($backfills, 1_000) as $chunk) {
            DB::table('cities')->upsert(
                $chunk,
                ['id'],
                ['ibge_code', 'state_id', 'name'],
            );
        }
    }
}
