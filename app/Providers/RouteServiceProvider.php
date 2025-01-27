<?php

namespace App\Providers;

use App\Models\ChannelLayer;
use App\Models\MediaMeta;
use App\Models\Schedule\ScheduleListing;
use App\Models\Schedule\ScheduleRule;
use App\Models\Scopes\IsNotExclusionScope;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });

        Route::model('layer', ChannelLayer::class);
        Route::model('schedule_layer', ChannelLayer::class);
        Route::model('listing', ScheduleListing::class);
        Route::model('schedule_listing', ScheduleListing::class);
        Route::model('file_meta', MediaMeta::class);

        // Let routes query exclusion rules
        Route::bind('rule', function ($value) {
            return ScheduleRule::withoutGlobalScope(IsNotExclusionScope::class)->findOrFail($value);
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
