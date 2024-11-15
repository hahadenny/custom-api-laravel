<?php

namespace App\Traits\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $company_id
 * @property-read \App\Models\Company $company
 */
trait BelongsToCompany
{
    public function company()
    {
        /** @var static|\Illuminate\Database\Eloquent\Model $this */
        return $this->belongsTo(Company::class);
    }

    public function scopeByCompanyId($query, int $companyId)
    {
        $query->where('company_id', $companyId);
    }

    public function scopeWithinCompany($query, Company $company)
    {
        $query->where('company_id', $company->id);
    }

    public function scopeWithinSameCompany($query, Model $model)
    {
        /** @var self|\Illuminate\Database\Eloquent\Model $model */
        $query->where('company_id', $model->company_id);
    }

    public function isInCompany(Company $company): bool
    {
        return $this->company_id === $company->id;
    }

    public function isInSameCompany(Model $model): bool
    {
        /** @var self|\Illuminate\Database\Eloquent\Model $model */
        return $this->company_id === $model->company_id;
    }
}
