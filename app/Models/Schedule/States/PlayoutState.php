<?php

namespace App\Models\Schedule\States;

use Spatie\ModelStates\Exceptions\InvalidConfig;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class PlayoutState extends State
{
    /*
     * Constants defining transitions allowed when transitioning to the specified state
     */

    /** @var string[] */
    protected const TO_IDLE_FROM = [Stopped::class, Playing::class, Next::class, Failed::class, Finished::class, Scheduled::class];

    /** @var string[] */
    protected const TO_PLAYING_FROM = [Stopped::class, Idle::class, Finished::class, Next::class, Paused::class, Failed::class, Scheduled::class];

    /** @var string[] */
    protected const TO_NEXT_FROM = [Stopped::class, Idle::class, Playing::class, Failed::class, Paused::class, Finished::class, Scheduled::class];

    /** @var string[] */
    protected const TO_PAUSED_FROM = [Stopped::class, Playing::class, Next::class];

    /** @var string[] */
    protected const TO_FAILED_FROM = [Stopped::class, Playing::class, Next::class, Idle::class, Paused::class, Scheduled::class];

    /** @var string[] */
    protected const TO_STOPPED_FROM = [Stopped::class, Playing::class, Next::class, Idle::class, Paused::class, Scheduled::class, Skipped::class, Failed::class, Finished::class];

    /**
     * @throws InvalidConfig
     */
    public static function config() : StateConfig
    {
        return parent::config()
                     ->default(Idle::class)
                     ->allowTransition(Playing::class, Finished::class)
                     ->allowTransition(
                         static::TO_IDLE_FROM,
                         Idle::class,
                     )
                     ->allowTransition(
                         static::TO_PLAYING_FROM,
                         Playing::class,
                     )
                     ->allowTransition(
                         static::TO_NEXT_FROM,
                         Next::class,
                     )
                     ->allowTransition(
                         static::TO_FAILED_FROM,
                         Failed::class,
                     )
                     ->allowTransition(
                         static::TO_PAUSED_FROM,
                         Paused::class,
                     )->allowTransition(
                        static::TO_STOPPED_FROM,
                        Stopped::class,
                    );

    }
}
