<?php

namespace App\Models;

use App\Status;
use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'status'])]
class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory, HasUuids;

    public function accessRoles(): HasMany
    {
        return $this->hasMany(AccessRole::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
        ];
    }
}
