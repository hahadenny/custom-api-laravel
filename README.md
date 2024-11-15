# Install project locally

- clone the repo (switch to `dev` branch)
- copy `.env.example` to `.env` (not rename)
- configure the `.env` variables
- run command:
````
  docker run --rm \
  -u "$(id -u):$(id -g)" \
  -v "$(pwd):/var/www/html" \
  -w /var/www/html \
  laravelsail/php81-composer:latest \
  composer install --ignore-platform-reqs \
  && php artisan key:generate \
  && php artisan jwt:secret
````
- Fill `GOOGLE_DEVELOPER_KEY` variable in `.env` file.
  - can be found in 1Password under Porta Google Developer Key
- `./vendor/bin/sail up` or `./vendor/bin/sail up -d`
- To stop: `./vendor/bin/sail stop`

## On First Run
Once sail is running, migrate and seed the database
```bash
# From WSL
./vendor/bin/sail artisan migrate --seed

# OR From docker sail container CLI
php artisan migrate --seed
```

Super admin credentials:
- username: `superadmin@disguise.one`
- password: `password`

# Debugging
[Laravel Telescope](https://laravel.com/docs/8.x/telescope) - When working locally (`APP_ENV=local`), requests, queries, events, etc., can be inspected via http://localhost/telescope if `TELESCOPE_ENABLED=true` in your .env file

---
## Sail Updates 
Update sail as needed and pseudo-re-publish the docker info
```bash
composer update laravel/sail --ignore-platform-reqs \
&& cp -r ./vendor/laravel/sail/runtimes/8.1/* ./docker/8.1 \
&& cp -r ./vendor/laravel/sail/runtimes/8.0/* ./docker/8.0 \
&& cp -r ./vendor/laravel/sail/runtimes/7.4/* ./docker/7.4
```

If there are also changes to docker-compose.yml, rebuild your existing containers:
```bash
./vendor/bin/sail build --no-cache
```

[Official Sail Docs](https://laravel.com/docs/9.x/sail)

# Workers
[Laravel Docs](https://laravel.com/docs/9.x/queues#configuring-supervisor)

Supervisor is set up to ensure necessary processes remain running in the background.

The `porta-worker` process starts 2 `queue:work` workers, config is located in `/etc/supervisor/conf.d/porta-worker.conf`:
```bash
[program:porta-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/html/porta-api/current/artisan horizon
autostart=true
autorestart=true
user=developer
# assign multiple workers to a queue and process jobs concurrently
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/html/logs/default_queue.log
```

It is restarted by the pipeline when deploying to staging and production.

---

# Troubleshooting
## ./vendor/bin/sail up doesn't finish building
If the `sail up` command stalls on `[4/11] apt-key adv --homedir ~/.gnupg...` Remove port 80 from ubuntu keyserver URL in Dockerfile that is being used. For example, `/docker/8.1/Dockerfile` > line 21
> See: https://github.com/laravel/sail/issues/503#issuecomment-1336273951

## Error on Login: “500 Internal Server Error” / “Could not create token: Key provided is shorter than 256 bits”
If you get this generic error, you may need to cache a new `JWT_SECRET`

Try the following steps:
- Run `./vendor/bin/sail artisan cache:clear` to clear your cache
- Run `./vendor/bin/sail artisan jwt:secret` to generate a token key, and confirm with yes
- Run `./vendor/bin/sail artisan config:cache` to cache the token key so your config can use it

You may also need to clear your browser cookies.

Then try logging in to Porta again

## ./vendor/bin/sail up error
```
Error response from daemon: Ports are not available: exposing port TCP 0.0.0.0:80 -> 0.0.0.0:0: listen tcp 0.0.0.0:80: bind: Only one usage of each socket address (protocol/network address/port) is normally permitted.
```

If sail fails, change the value of `APP_PORT` in `.env` to tell docker to use another port. Try `8080` or `8000`

## Error: “bash \r no such file or directory”
When trying to run npm commands, this usually means WSL is trying to use NodeJS from windows.

Run `which npm` to check, if the path is `/mnt/c/`, then you need to [install npm inside WSL](https://d3technologies.atlassian.net/wiki/spaces/PORTA/pages/1714815165/Porta+Local+Development+Setup#Install-NodeJS-%26-npm)


## When try to upload with media manager: `Missing required client configuration options: region`
Make sure `AWS_DEFAULT_REGION` has a value in your `.env` file, i.e. `us-east-1`
