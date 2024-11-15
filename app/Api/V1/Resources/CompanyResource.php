<?php

namespace App\Api\V1\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        /** @var \App\Models\Company $company */
        $company = $this->resource;
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if ($user) {
            if ($user->isAdmin()) {
                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'ue_url' => $company->ue_url,
                    'api_key' => $company->api_key,
                    'settings' => $company->settings,
                ];
            }

            if ($user->isSuperAdmin()) {
                $cluster = $company->cluster;
                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'description' => $company->description,
                    'ue_url' => $company->ue_url,
                    'is_active' => $company->is_active,
                    'api_key' => $company->api_key,
                    'settings' => $company->settings,
                    'created_at' => $company->created_at,
                    'updated_at' => $company->updated_at,
                    'cluster' => $company->cluster ? [
                        'name' => $cluster->name,
                        'region' => $cluster->region,
                    ] : null
                ];
            }
        }

        return [
            'id' => $company->id,
            'name' => $company->name,
        ];
    }
}
