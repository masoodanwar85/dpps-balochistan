<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait BelongsToVisibleCompany
{
    public function resolveRouteBinding($value, $field = null): Model
    {
        $row = $this->newQuery()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();

        if (! $row instanceof Model || ! isset($row->company_id)) {
            abort(404);
        }

        $user = Auth::user();
        $visible = Company::query()
            ->visibleTo($user instanceof User ? $user : null)
            ->whereKey($row->company_id)
            ->exists();

        if (! $visible) {
            abort(404);
        }

        return $row;
    }
}
