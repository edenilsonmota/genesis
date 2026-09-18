<?php

namespace App\Models;

use App\Enums\MemberImportOperation;
use App\Enums\MemberImportRowStatus;
use Database\Factories\MemberImportRowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['member_import_id', 'row_number', 'operation', 'member_id', 'normalized_payload', 'errors', 'status'])]
class MemberImportRow extends Model
{
    /** @use HasFactory<MemberImportRowFactory> */
    use HasFactory, HasUuids;

    public function memberImport(): BelongsTo
    {
        return $this->belongsTo(MemberImport::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    protected function casts(): array
    {
        return [
            'operation' => MemberImportOperation::class,
            'normalized_payload' => 'array',
            'errors' => 'array',
            'status' => MemberImportRowStatus::class,
        ];
    }
}
