<?php

namespace App\Services\Portal;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class PortalAccess
{
    public function company(User $actor): Company
    {
        if ($actor->user_type !== 'company' || ! $actor->can('portal.access') || $actor->company_id === null) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $company = Company::query()->whereKey($actor->company_id)->first();

        if (! $company instanceof Company) {
            abort(404);
        }

        return $company;
    }
}
