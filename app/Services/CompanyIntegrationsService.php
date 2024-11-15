<?php

namespace App\Services;

use App\Models\CompanyIntegrations;
use App\Models\User;

class CompanyIntegrationsService
{
    public function store(User $authUser, array $params = []): CompanyIntegrations
    {
        $integration = $authUser->company->companyIntegrations()->firstOrNew(['type' => $params['type']]);
        $integration->fill($params)
            ->save();
        return $integration;
    }

}
