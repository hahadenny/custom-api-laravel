<?php

namespace App\Services;

use App\Contracts\Models\ItemInterface;
use App\Contracts\Models\ListingPivotInterface;
use App\Contracts\Models\ScheduleableInterface;
use App\Contracts\Models\ScheduleableParentInterface;
use App\Enums\Schedule\ScheduleOrigin;
use App\Models\Channel;
use App\Models\ChannelGroup;
use App\Models\Company;
use App\Models\Schedule\Schedule;
use App\Models\User;
use App\Traits\Services\ItemTrait;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RRule\RSet;

class ChannelService
{
    use ItemTrait;

    protected function getItemClass(): string
    {
        return Channel::class;
    }

    protected function getGroupClass(): string
    {
        return ChannelGroup::class;
    }

    protected function getGroupService(): ChannelGroupService
    {
        return new ChannelGroupService();
    }

    public function getItemListingPivot(Channel|ItemInterface $item): ListingPivotInterface
    {
        return $item->parentListingPivot()->withTrashed()->firstOrNew([
            'company_id' => $item->company_id,
        ]);
    }

    protected function getQueryItemsWithoutGroup(Channel|ItemInterface $item): Relation
    {
        return $item->company->channels()->whereNull('channel_group_id');
    }

    protected function getParamGroupName(): string
    {
        return 'channel_group_id';
    }

    public function listing(User $authUser): SupportCollection
    {
        $company = $authUser->company;

        $channels = $company->listingChannels()
            ->with([
                'parentListingPivot',
                'createdBy:id,first_name,last_name,email',
                'parent:id,name,type,is_preview'
            ])
            ->wherePivotNull('group_id')
            ->orderByPivot('sort_order')
            ->get();

        /** @var \Kalnoy\Nestedset\Collection $channelGroups */
        $channelGroups = $company->listingChannelGroups()
            ->with([
                'parentListingPivot',
                'listingChannels' => function (MorphToMany $morphToMany) {
                    $morphToMany->orderByPivot('sort_order');
                },
                'listingChannels.parentListingPivot',
                'listingChannels.createdBy:id,first_name,last_name,email',
                'listingChannels.parent:id,name,type,is_preview',
            ])
            ->orderByPivot('sort_order')
            ->get();

        $channels->makeHidden(['group']);
        $channelGroups->makeHidden(['children', 'items', 'channels', 'listingChannels']);

        foreach ($channelGroups as $channelGroup) {
            /** @var \App\Models\ChannelGroup $channelGroup */
            $channelGroup->listingChannels->makeHidden(['group']);
            $channelGroup->setRelation('items', $channelGroup->listingChannels);
        }

        return ChannelGroupService::sortTreeComponents($channelGroups->toTree()->toBase()->merge($channels));
    }

    public function store(User $authUser, array $params = []): Channel
    {
        $channelParams = $params;
        unset($channelParams['sort_order']);

        $channel = new Channel($channelParams);

        $channel->createdBy()->associate($authUser);
        $channel->company()->associate($authUser->company);

        DB::transaction(function () use ($channel, $params) {
            $channel->save();

            $listingPivot = $this->getItemListingPivot($channel);
            $listingPivot->group()->associate($channel->channel_group_id);
            $listingPivot->saveOrRestore();

            if (isset($params['sort_order'])) {
                $listingPivot->moveToOrder($params['sort_order']);
            } else {
                $listingPivot->updateSortOrderOfList();
            }
        });

        return $channel;
    }

    public function update(Channel $channel, array $params = []): Channel
    {
        DB::transaction(function () use ($channel, $params) {
            $oldGroup = $channel->group;

            $channelParams = $params;
            unset($channelParams['sort_order']);
            if (isset($channelParams['user_timezone'])){
                unset($channelParams['user_timezone']);
            }

            $channel->update($channelParams);
            $channel->refresh();

            $listingPivot = $this->getItemListingPivot($channel);
            $listingPivot->group()->associate($channel->channel_group_id);
            $listingPivot->saveOrRestore();

            if ($channel->channel_group_id !== $oldGroup?->id) {
                $listingPivot->setHighestOrderNumber();
                $listingPivot->saveOrRestore();
            }

            if (isset($params['sort_order'])) {
                $listingPivot->moveToOrder($params['sort_order']);
            } else {
                $listingPivot->updateSortOrderOfList();
            }
        });

        return $channel;
    }

    public function batchUpdate(array $params = []): Collection
    {
        return $this->baseBatchUpdate($params);
    }

    public function batchDuplicate(User $authUser, array $params = []): void
    {
        $this->baseBatchDuplicate($authUser, $params);
    }

    public function delete(Channel $channel): void
    {
        DB::transaction(function () use ($channel) {
            $listingPivot = $this->getItemListingPivot($channel);

            $channel->delete();

            $listingPivot->updateSortOrderOfList();
        });
    }

    public function batchDelete(array $params = []): void
    {
        $this->baseBatchDelete($params);
    }

    public function sync(?Company $company, ?User $user, array $params = []): void
    {
        DB::transaction(function () use ($company, $user, $params) {
            if (!is_null($user)) {
                $company = $user->company;
            }
            $group = null;
            if (!empty($params['assign_to_default'])) {
                $group = $company->channelGroups()->first();
            }

            $listingPivotUpdateList = null;

            foreach ($params['channels'] as $row) {
                if (!empty($row['parent_name'])) {
                    $parent = $company->channels()->where(['name' => $row['parent_name']])->first();
                    $row['parent_id'] = $parent?->id;
                }
                $attrs = ['name' => $row['name']];
                if (!empty($row['parent_id'])) {
                    $attrs['parent_id'] = $row['parent_id'];
                }
                /** @var \App\Models\Channel $channel */
                $channel = $company->channels()->firstOrNew($attrs);
                if ($group) {
                    $row['channel_group_id'] = $group->id;
                }
                $channel->fill($row);
                if (!$channel->exists && !is_null($user)) {
                    $channel->createdBy()->associate($user);
                }
                $channel->save();

                $listingPivot = $this->getItemListingPivot($channel);
                $listingPivot->group()->associate($channel->channel_group_id);
                $listingPivot->setHighestOrderNumber();
                $listingPivot->saveOrRestore();

                if (is_null($listingPivotUpdateList)) {
                    $listingPivotUpdateList = $listingPivot;
                }
            }

            if (! is_null($listingPivotUpdateList)) {
                $listingPivotUpdateList->updateSortOrderOfList();
            }
        });
    }

    public function buildTreeToUpdateSort(Company $company): SupportCollection
    {
        $channels = $company->listingChannels()
            ->wherePivotNull('group_id')
            ->orderByPivot('sort_order')
            ->orderByPivot('id')
            ->get();

        /** @var \Kalnoy\Nestedset\Collection $channelGroups */
        $channelGroups = $company->listingChannelGroups()
            ->with([
                'listingChannels' => function (MorphToMany $morphToMany) {
                    $morphToMany
                        ->orderByPivot('sort_order')
                        ->orderByPivot('id');
                },
            ])
            ->orderByPivot('sort_order')
            ->orderByPivot('id')
            ->get();

        foreach ($channelGroups as $channelGroup) {
            /** @var \App\Models\ChannelGroup $channelGroup */
            $channelGroup->setRelation('items', $channelGroup->listingChannels);
        }

        return ChannelGroupService::sortTreeComponents($channelGroups->toTree()->toBase()->merge($channels));
    }
}
