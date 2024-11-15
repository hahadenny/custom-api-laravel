<?php

namespace App\Services\Schedule;

use App\Models\ChannelLayer;
use App\Models\Schedule\ScheduleListing;
use App\Models\Schedule\ScheduleSet;
use App\Models\User;
use App\Services\Schedule\Helpers\ScheduleFactory;
use App\Traits\Services\UniqueNameTrait;
use Illuminate\Database\Eloquent\Collection;

class ChannelLayerService
{
    use UniqueNameTrait;

    public function __construct(protected ScheduleFactory $scheduleFactory)
    {
    }

    /**
     * Display a listing of channel layers
     *
     * @return Collection
     */
    public function listing(ScheduleSet $scheduleSet)
    {
        return ChannelLayer::with(['playlists', 'pages'])->where('schedule_set_id', $scheduleSet->id)->get();
    }

    public function store(User $authUser, ScheduleSet $scheduleSet, array $params = []) : ChannelLayer
    {
        $sort_order = $params['sort_order'] ?? null;
        unset($params['sort_order']);
        $layer = new ChannelLayer($params);
        $layer->createdBy()->associate($authUser);
        $layer->company()->associate($authUser->company);
        $layer->scheduleSet()->associate($scheduleSet);
        $layer->save();

        // create in schedule listing
        $scheduleListing = new ScheduleListing([
            'schedule_set_id' => $scheduleSet->id,
            'scheduleable_id' => $layer->id,
            'scheduleable_type' => $layer::class,
        ]);
        if (isset($sort_order)) {
            $scheduleListing->moveToOrder($sort_order);
        } else {
            $scheduleListing->setHighestOrderNumber();
        }
        $scheduleListing->saveOrRestore();
        return $layer;
    }

    public function update(User $authUser, ScheduleSet $scheduleSet, ChannelLayer $layer, array $params = []) : ChannelLayer
    {
        $layer->update($params);
        $layer->refresh();

        return $layer;
    }

    public function duplicate(ChannelLayer $layer, array $params = []) : ChannelLayer
    {
        // todo: implement
    }

    public function delete(ChannelLayer $layer) : void
    {
        $layer->delete();
    }

    public function batchDelete(array $params = []): void
    {
        $layers = ChannelLayer::whereIn('id', $params['ids'])->get();
        // loop so we ensure the model events are fired
        foreach($layers as $layer){
            $layer->delete();
        }
    }
}
