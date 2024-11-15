<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user=null) {
            if( config('app.onprem') && config('app.airgapped') ){
                return true;
            }

            return false;

            // Code below currently won't work because there is no user we can get from the auth guards
            // that will work here. Dingo refuses to get auth from default routed requests and there is
            // no logged-in user when accessing the API directly in browser

            // $user ??= Auth::guard('api-key')->user();
            // ray($user)->label("user");
            // return $user->isSuperAdmin();
        });
    }
}
