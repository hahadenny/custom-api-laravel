# Upgrade Guide

## Upgrading Laravel To 9.x From 8.x

1. Stop Sail. `./vendor/bin/sail stop`.
2. Pull changes with the new Laravel version. `git pull ...`.
3. Update ".env" file with the changes from the ".env.example" file.
    - Rename "FILESYSTEM_DRIVER" to "FILESYSTEM_DISK".
    - Remove "MIX_PUSHER_APP_KEY", "MIX_PUSHER_APP_CLUSTER".
    - After "PUSHER_APP_SECRET", add
    ```
    PUSHER_HOST=
    PUSHER_PORT=443
    PUSHER_SCHEME=https
    ```
    After "PUSHER_APP_CLUSTER", add
    ```
    VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
    VITE_PUSHER_HOST="${PUSHER_HOST}"
    VITE_PUSHER_PORT="${PUSHER_PORT}"
    VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
    VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
    ```
    See ".env.example" file.
4. In your Ubuntu/WSL terminal, run:
    ```
    docker run --rm \
        -u "$(id -u):$(id -g)" \
        -v "$(pwd):/var/www/html" \
        -w /var/www/html \
        laravelsail/php81-composer:latest \
        composer install --ignore-platform-reqs
    ```
5. Run `./vendor/bin/sail build`.
6. Run `./vendor/bin/sail up` to recreate your containers and start Sail again

In your .env file, if `JWT_SECRET=my-dummy-token` or similar:
1. Run `php artisan cache:clear`
2. Run `yes | php artisan jwt:secret`
3. Run `php artisan config:cache`

For more details:

- https://laravel.com/docs/9.x/upgrade
- https://github.com/laravel/laravel/compare/8.x...9.x
