# Scheduler

## Setting Up Laravel Task Scheduling with systemd
> https://www.freedesktop.org/software/systemd/man/systemd.timer.html#Options
> https://www.freedesktop.org/software/systemd/man/systemd.time.html

### On Remote Server
Setup the laravel scheduler using systemd instead of crond by running `setup_laravel_tasks.sh`:
```bash
sudo bash /var/www/html/porta-api/current/setup_laravel_tasks.sh
```
This will allow more frequent scheduling than by minute only.

The `laravel-schedule-run` service is set to run **every 1 second** by default.

#### Change the systemd frequency
Change the laravel scheduler's run frequency by updating the `OnUnitActiveSec` field in `laravel-schedule-run.timer`

### Local Dev
Docker and `php artisan:serve` cannot utilize systemd. To simulate the shorter systemd task scheduling locally, ensure the dev package `laravel-cronless-schedule` is installed via `composer install`

Then initiate checking for scheduled tasks every 1 second to simulate systemd service:
```bash
php artisan schedule:run-cronless --frequency=1 
```
To stop running after `X` seconds
```bash
php artisan schedule:run-cronless --frequency=1 --stop-after-seconds=5
```

## Broadcasting
The task run for Porta's Scheduler uses Redis to broadcast directly to the socket server.

### Setup
In `.env`, ensure `BROADCAST_DRIVER=redis`

#### Local Dev
New local installs can skip the next 3 steps, but existing installs will need to update their docker setup to include Redis, which has been added to `docker-compose.yml`:
1. Stop any running containers: `./vendor/bin/sail stop`
2. Update your Docker setup: `./vendor/bin/sail build`
3. Restart your containers from the new images: `./vendor/bin/sail up -d`
    - (Existing mysql data should not be affected)

##### Access Redis CLI in Docker
To run Redis commands, enter the redis CLI from WSL, while the redis container is running:
```bash
docker exec -it <redis-container-id> redis-cli
```

##### Monitor Redis
Access `redis-cli` and run `MONITOR` to monitor all Redis activity.
```shell
$ redis-cli
127.0.0.1:6379> MONITOR
OK
```

To monitor all broadcasts instead of all activty, run `PSUBSCRIBE *`
```shell
127.0.0.1:6379> PSUBSCRIBE *
> Reading messages... (press Ctrl-C to quit)
> 1) "psubscribe"
> 2) "*"
> 3) (integer) 1
```

Replace `*` with a specific channel or [valid pattern](https://redis.io/commands/psubscribe/) to limit the broadcasts that you are monitoring
```shell
# monitor any channel
127.0.0.1:6379> PSUBSCRIBE *-API-msg

# monitor a specific channel
127.0.0.1:6379> PSUBSCRIBE PreviewB-API-msg
```

## Database 

Create table for job batches data
```shell
php artisan queue:batches-table

php artisan migrate
```
> See: https://laravel.com/docs/9.x/queues#job-batching
