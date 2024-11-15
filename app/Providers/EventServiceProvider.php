<?php

namespace App\Providers;

use App\Events\PasswordWasChanged;
use App\Events\PlayoutFinished;
use App\Events\SocketServerCreated;
use App\Events\TerraformSocketRunCreated;
use App\Listeners\CheckEndedEventListener;
use App\Listeners\CheckStartingEventListener;
use App\Listeners\CreateOhDearSite;
use App\Listeners\CreateSocketServer;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\MediaVideoConverterListener;
use App\Listeners\PrimaryDBCheckEndedEventListener;
use App\Listeners\RunManyHealthChecksEndedEventListener;
use App\Listeners\SendNewPasswordEmail;
use App\Listeners\SocketCheckEndedEventListener;
use App\Models\PlaylistListing;
use App\Models\PlayoutHistory;
use App\Models\ProjectListing;
use App\Models\Schedule\ScheduleListing;
use App\Observers\ListingObserver;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Spatie\Health\Events\CheckEndedEvent;
use Spatie\Health\Events\CheckStartingEvent;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAdded;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class                => [
            SendEmailVerificationNotification::class,
        ],
        Login::class                     => [
            LogSuccessfulLogin::class,
        ],
        PasswordWasChanged::class        => [
            SendNewPasswordEmail::class,
        ],
        SocketServerCreated::class       => [
            CreateSocketServer::class
        ],
        TerraformSocketRunCreated::class => [
            CreateOhDearSite::class
        ],
        MediaHasBeenAdded::class => [
            MediaVideoConverterListener::class,
        ]
    ];

    /**
     * The model observers for your application.
     *
     * @var array
     */
    protected $observers = [
        PlaylistListing::class => [ListingObserver::class],
        ProjectListing::class => [ListingObserver::class],
        ScheduleListing::class => [ListingObserver::class],
    ];

    public function __construct($app)
    {
        if(config('app.onprem')){
            $this->listen[CheckStartingEvent::class]= [
                CheckStartingEventListener::class,
            ];
            $this->listen[CheckEndedEvent::class]= [
                CheckEndedEventListener::class,
                PrimaryDBCheckEndedEventListener::class,
                SocketCheckEndedEventListener::class
            ];
            $this->listen[CommandFinished::class]= [
                RunManyHealthChecksEndedEventListener::class
            ];
        }

        if(!config('services.scheduler.enabled')){
            $this->observers = [];
        }

        parent::__construct($app);
    }

    public function boot()
    {
        Event::listen(function (PlayoutFinished $event) {
            PlayoutHistory::create([
                'channel_id' => $event->playout->playout_channel_id,
                'listing_type' => ScheduleListing::class,
                'listing_id' => $event->playout->schedule_listing_id,
                'status' => $event->playout->status,
            ]);
        });
    }
}
