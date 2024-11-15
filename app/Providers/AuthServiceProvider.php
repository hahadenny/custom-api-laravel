<?php

namespace App\Providers;

use App\Models\MediaMeta;
use App\Models\SocketServer;
use App\Models\User;
use App\Policies\FileMetaPolicy;
use App\Policies\FilePolicy;
use App\Policies\PermissionPolicy;
use App\Policies\SocketServerPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Media::class => FilePolicy::class,
        MediaMeta::class => FileMetaPolicy::class,
        SocketServer::class => SocketServerPolicy::class,
        Permission::class => PermissionPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        ResetPassword::createUrlUsing(function ($user, string $token) {
            return config('app.front_url').'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });

        Auth::viaRequest('api-key', function (Request $request) {
            return $this->app['Dingo\Api\Auth\Auth']->user();
        });

        Auth::viaRequest('health-secret', function (Request $request) {
            return $this->app['Dingo\Api\Auth\Auth']->user();
        });

        // defines the Auth Guard for the 's3-plugins' driver
        Auth::viaRequest('s3-plugins', function (Request $request) {
            if ($request->bearerToken() !== config('filesystems.disks.s3-plugin.secret')) {
                throw new UnauthorizedHttpException('Bearer', 'Unable to authenticate: invalid S3 key as Bearer token.');
            }
            return User::query()->isSuperAdmin()->orderBy('id')->firstOrFail();
        });
    }
}
