<?php

namespace App\Models;

use Database\Factories\StateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ibge_code', 'abbreviation', 'name'])]
class State extends Model
{
    /** @use HasFactory<StateFactory> */
    use HasFactory;

    public $timestamps = false;

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}
