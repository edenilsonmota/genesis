<?php

namespace App\Http\Requests\Position;

use App\Models\Position;

class UpdatePositionRequest extends StorePositionRequest
{
    public function authorize(): bool
    {
        $position = $this->route('position');

        return $position instanceof Position && ($this->user()?->can('update', $position) ?? false);
    }
}
