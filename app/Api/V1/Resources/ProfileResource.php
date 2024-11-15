<?php

namespace App\Api\V1\Resources;

use App\Models\ChannelPermissionable;
use App\Services\ImpersonateService;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        /** @var \App\Models\User $user */
        $user = $this->resource;
        /** @var \App\Services\ImpersonateService $impersonateService */
        $impersonateService = app(ImpersonateService::class);

        return [
            'id' => $user->id,
            'email' => $user->email,
            'company_id' => $user->company_id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'role' => $user->role,
            'last_login_at' => $user->last_login_at,
            'settings' => $user->profile->settings,
            'user_project' => $user->userProject,
            'company_ue_url' => $this->when($user->isAdmin() || $user->isUser(), fn () => $user->company->ue_url),
            'is_impersonating' => $impersonateService->isImpersonating(),
            'company' => $this->when(! is_null($user->company), function () use ($user) {
                return [
                    'id' => $user->company->id,
                    'name' => $user->company->name,
                    'description' => $user->company->description,
                    'ue_url' => $user->company->ue_url,
                    'is_active' => $user->company->is_active,
                    'api_key' => $this->when($user->isAdmin(), $user->company->api_key),
                    'settings' => $user->company->settings,
                    'created_at' => $user->company->created_at,
                    'updated_at' => $user->company->updated_at,
                ];
            }),
            'permissions' => [
                'user' => $user->permissions->pluck('name'),
                'channel' => $user->group ?
                    $user->group->channelPermissionables->mapWithKeys(fn(ChannelPermissionable $cp) => [
                        $cp->channel_id => $cp->permissions()->pluck('name')
                    ]) :
                    [],
            ]
        ];
    }
}
