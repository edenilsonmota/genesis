<?php

namespace App\Models;

use App\Enums\MemberImportStatus;
use Database\Factories\MemberImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['church_id', 'uploaded_by_user_id', 'confirmed_by_user_id', 'original_filename', 'stored_filename', 'file_path', 'file_hash', 'total_rows', 'new_members_count', 'updates_count', 'errors_count', 'status', 'validated_at', 'confirmed_at', 'processed_at', 'completed_at', 'failed_at', 'failure_reason'])]
class MemberImport extends Model
{
    /** @use HasFactory<MemberImportFactory> */
    use HasFactory, HasUuids;

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(MemberImportRow::class);
    }

    protected function casts(): array
    {
        return [
            'status' => MemberImportStatus::class,
            'validated_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'processed_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
