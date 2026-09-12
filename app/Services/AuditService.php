<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditService
{
    /** @param array<string, mixed> $details */
    public function record(
        string $action,
        string $resource,
        Model|string|null $record = null,
        ?string $scopeType = null,
        ?string $scopeId = null,
        array $details = [],
    ): AuditLog {
        $actor = auth()->user();

        return AuditLog::query()->create([
            'actor_user_id' => $actor?->id,
            'actor_name' => $actor?->display_name,
            'actor_username' => $actor?->username,
            'action' => $action,
            'resource' => $resource,
            'route' => request()?->route()?->getName(),
            'record_id' => $record instanceof Model ? (string) $record->getKey() : $record,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'ip_address' => request()?->ip(),
            'details' => $this->sanitize($details),
        ]);
    }

    /** @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private function sanitize(array $details): array
    {
        return collect($details)
            ->reject(fn (mixed $value, string $key): bool => Str::contains(Str::lower($key), [
                'password', 'senha', 'hash', 'token', 'secret',
            ]))
            ->map(fn (mixed $value): mixed => is_array($value) ? $this->sanitize($value) : $value)
            ->all();
    }
}
