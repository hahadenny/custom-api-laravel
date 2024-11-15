<?php

namespace App\Services\Schedule;

use App\Jobs\PauseLoopJob;
use App\Jobs\PlayLoopJob;
use App\Jobs\StopLoopJob;
use App\Models\Project;
use App\Models\Schedule\ScheduleSet;
use App\Models\Schedule\States\Idle;
use App\Models\Schedule\States\Paused;
use App\Models\Schedule\States\Playing;
use App\Models\Schedule\States\Stopped;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

class ScheduleSetService
{
    public function __construct(protected ChannelLayerService $layerService)
    {
    }

    public function listing(Project $project, User $authUser)
    {
        $listing =  ScheduleSet::where('project_id', $project->id)
                          ->with('activeUsers')
                          ->orderBy('name')
                          ->get();
        return $listing;
    }

    public function store(Project $project, array $params = [])
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::guard()->user();

        return DB::transaction(function() use ($params, $authUser) {
            $set = new ScheduleSet($params);
            $set->createdBy()->associate($authUser);
            $set->save();

            $this->setOtherScheduleSetsInactiveForUser($authUser, $set);
            $authUser->scheduleSets()->attach($set->id, ['is_active' => 1]);

            // create default layer to go with it
            $this->layerService->store($authUser, $set, [
                'name' => 'Default Layer',
            ]);

            return $set;
        });
    }

    public function update(Project $project, ScheduleSet $set, array $params = [])
    {
        if (isset($params['status'])) {
            DB::transaction(function () use ($project, $set, $params) {
                /** @var \App\Models\User $authUser */
                $authUser = Auth::guard()->user();
                $user_timezone = $params['user_timezone'] ?? null;
                $this->changeStatus($set, $params['status'], $authUser, $user_timezone, true);
            });
        }

        return $set;
    }

    // update pivot or attach new record designating the active schedule set for the given user
    // scoped by project_id
    public function updateActive(User $user, ScheduleSet $scheduleSet)
    {
        $this->setOtherScheduleSetsInactiveForUser($user, $scheduleSet);
        if($user->whereRelation(
                'scheduleSets',
                'schedule_set_user.schedule_set_id',
                '=',
                $scheduleSet->id)->count() > 0){
            $user->scheduleSets()->updateExistingPivot($scheduleSet->id, ['is_active' => true]);
        } else {
            $user->scheduleSets()->attach($scheduleSet->id, ['is_active' => 1]);
        }

        return $scheduleSet;
    }

    /**
     * @throws \Exception
     */
    private function changeStatus(ScheduleSet $set, string $status, User $user, $user_timezone=null, bool $after_commit = false) : void
    {
        // make sure status is set before we transition states
        $set->status ??= Idle::class;

        try{
            $set->status->transitionTo($status);
            $set->save();
        } catch (TransitionNotFound $e){
            // something like 'playing' to 'playing' was attempted
            throw new \Exception("[TransitionNotFound] ScheduleSet cannot be set from '{$set->status}' to '{$status}'");
        } catch (CouldNotPerformTransition $e) {
            throw new \Exception("[CouldNotPerformTransition] ScheduleSet cannot be set from '{$set->status}' to '{$status}'");
        }

        $job = match($set->status::$name){
            Paused::$name => PauseLoopJob::class,
            Playing::$name => PlayLoopJob::class,
            Stopped::$name => StopLoopJob::class,
            default => null,
        };

        if(!isset($job)){
            return;
        }

        $after_commit ? $job::dispatch($set, $user, $user_timezone)->afterCommit()
            : $job::dispatch($set, $user, $user_timezone);
    }

    // scoped by project_id
    private function setOtherScheduleSetsInactiveForUser(User $user, ScheduleSet $scheduleSet) : void
    {
        // get all other ScheduleSets, for this user and project, that should switch to inactive
        // (there really should only be one)
        $inactiveSets = ScheduleSet::select('id')
                                   ->where('schedule_sets.id', '<>', $scheduleSet->id)
                                   ->where('project_id', '=', $scheduleSet->project_id)
                                   ->whereRelation('activeUsers', 'schedule_set_user.user_id', '=', $user->id)
                                   ->get();
        if(empty($inactiveSets)){
            return;
        }
        // sync with pivot values without detaching
        $user->scheduleSets()->syncWithPivotValues($inactiveSets, ['is_active' => false], false);
    }
}
