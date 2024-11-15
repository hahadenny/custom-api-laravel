# Porta Installer & Helper Logs

The installer script will save most of its output to a log file within `/home/$USER/porta/logs`, usually inside the `installer` directory.


--- 

_Note: The container must be running in order to access it._

# Access A Docker Container's Command Line Interface (CLI)
From a WSL terminal, run `docker exec -it <container name> bash`

# Docker Desktop UI
## Access Docker container logs from the Docker GUI
1. From the Docker Desktop Dashboard left sidebar, select Containers

2. Click the name of the container you would like to view

3. In the top horizontal menu, select "Logs" to view the main log file contents of the container (The "Logs" item is selected by default)

## Access Docker container files from the GUI
> **!NOTE!:** This functionality should only be used under the guidance of a Disguise engineer, and only in emergency circumstances.

1. From the Docker Desktop Dashboard left sidebar, select Containers

2. Click the name of the container you would like to view

3. In the top horizontal menu, select "Files" to view the root directory of the container

4. To edit a file, right click on it and select "Edit file". A panel containing the file contents will appear

5. After editing a file, save changes with the save icon in the top right of the file edit panel

## Docker Logs Locations
To find the location of the logs for a specific container, you can run the following command:
```bash
docker inspect --format='{{.LogPath}}' containername
```
Example output: 
```bash
/var/lib/docker/containers/f844a7b45ca5a9589ffaa1a5bd8dea0f4e79f0e2ff639c1d010d96afb4b53334/f844a7b45ca5a9589ffaa1a5bd8dea0f4e79f0e2ff639c1d010d96afb4b53334-json.log
```

---

# Porta Application Container
- Container name: `porta`
- Ports: 8080 (UI), 8000 (API)

## CLI Access
```bash
docker exec -it porta bash
```

## Installer Logs
Porta installer logs are located in the WSL directory: `/home/$USER/porta/logs/installer`

## Configuration Files
### Nginx Configuration
- nginx.conf file: `/etc/nginx/sites-available/default`
  - `docker exec -it porta bash -c "cat /etc/nginx/sites-available/default"` 
  - Defaults: 
    - `client_max_body_size 1024M;`

#### Supervisor Configuration
- supervisor.conf file: `/etc/supervisord.conf`
  - `docker exec -it porta bash -c "cat /etc/supervisord.conf"`

### PHP Configuration
- PHP INI file: `/usr/local/etc/php/conf.d/app.ini`
  - `docker exec -it porta bash -c "cat /usr/local/etc/php/conf.d/app.ini"`
  - Defaults:
    - `post_max_size=1024M`
    - `upload_max_filesize=1000M`

### Porta API Configuration
- API files: `/var/www/`
- API environment file: `/var/www/.env`
    - `docker exec -it porta bash -c "cat .env"`
- API Config files (for Laravel): `/var/www/config`
  - Services (Bridge, etc.): `config/services.php`
  - Database: `config/database.php`
  - Backups: `config/backup.php`
  - Media: `config/media-library.php`
    - Defaults: 
      - `'max_file_size' => 1024 * 1024 * 1000, // 1000MB`
      - `'image_driver' => 'imagick'`
      - `'ffmpeg_path' => '/usr/bin/ffmpeg'`
      - `'ffprobe_path' => '/usr/bin/ffprobe'`
- API cached configuration: `/var/www/bootstrap/cache/config.php`
  - docker exec -it porta bash -c "cat bootstrap/cache/config.php"


### Porta UI Configuration
- Front End files: `/var/www/frontend`
- Front End environment file: `/var/www/frontend/.env`
  - `docker exec -it porta bash -c "cat frontend/.env"`


## Logs
### Porta API Logs
- Default API error logs location: `/var/www/storage/logs`
  - _Note: The log filenames will be scoped by date, i.e. `/var/www/storage/logs/laravel-YYYY-MM-DD.log`_
  - `docker exec -it porta bash -c "cat /var/www/storage/logs/laravel-$(date +'%Y-%m-%d').log"`
- PHP error logs: `/var/log/php/errors.log`
- Queue logs (for play group sequence): `/var/www/logs/default_queue.log`
_Entries should end in `DONE`_
- Tasks logs (for scheduled tasks like database backups): `/var/www/logs/default_tasks.log`
- Horizon logs (queue dashboard): `/var/www/logs/horizon.log`
- UI  logs: can be viewed in the Web Browser console
  - _To access the logs, open the browser's developer tools (F12) and select the `Console` tab_


Normal `Logs` content on container startup displayed in the docker logs UI will look similar to:
```
INFO supervisord started with pid 1
INFO spawned: 'nginx' with pid 7
INFO spawned: 'php-fpm' with pid 8
INFO spawned: 'porta-horizon-worker_00' with pid 9
INFO spawned: 'porta-horizon-worker_01' with pid 10
INFO success: nginx entered RUNNING state, process has stayed up for > than 1 seconds (startsecs)
INFO success: php-fpm entered RUNNING state, process has stayed up for > than 1 seconds (startsecs)
INFO success: porta-horizon-worker_00 entered RUNNING state, process has stayed up for > than 1 seconds (startsecs)
INFO success: porta-horizon-worker_01 entered RUNNING state, process has stayed up for > than 1 seconds (startsecs)
INFO success: porta-task-worker_00 entered RUNNING state, process has stayed up for > than 1 seconds (startsecs)
INFO success: porta-task-worker_01 entered RUNNING state, process has stayed up for > than 1 seconds (startsecs)
```

## Commands

### View Porta Version
```bash
docker exec -it porta bash -c "php artisan check:version"
```
Result Example:
```bash
+----------------------+-------------------------------------------------------------------------------------------------+
| Name                 | Info                                                                                            |
+----------------------+-------------------------------------------------------------------------------------------------+
| Porta Machine        | main                                                                                            |
| Porta Version        | 2.3.13-features/dev-PN-979-backups+00034                                                        |
| Porta Version Number | 2.3.13                                                                                          |
| Porta Build          | 00034                                                                                           |
| Porta Version Date   | 2024-01-23 11:55:11                                                                             |
| Laravel              | 9.33.0                                                                                          |
| PHP                  | 8.1.27                                                                                          |
| MySQL                | 8.0.32                                                                                          |
| Redis                | ERROR -- NOT AVAILABLE                                                                          |
| OS                   | Linux 998c80a9d1d4 5.15.133.1-microsoft-standard-WSL2 #1 SMP Thu Oct 5 21:02:42 UTC 2023 x86_64 |
+----------------------+-------------------------------------------------------------------------------------------------+
```

### Check for Primary Database Connection
```bash
docker exec -it porta bash -c "php artisan health:single-check 'PrimaryDatabaseConnectionCheck' --do-not-store-results --no-notification --fail-command-on-failing-check"
```
Result Example:
```bash
Running health check...

Running check: Primary Database Connection...
Ok: Found online primary: {"member_host":"192.168.50.42","member_port":3308}

All done!
```

### Check Porta's active database connection
```bash
docker exec -it porta bash -c "cat .env | grep DB_HOST"
```
This will result in something like:
```dotenv
DB_HOST_1=192.168.50.163
DB_HOST_2=192.168.50.20
DB_HOST_3=192.168.50.42
DB_HOST="${DB_HOST_3}"
```

The `DB_HOST` variable should be set to the primary database's `DB_HOST_#` variable or IP address.

_In this example, the primary database has a `MEMBER_HOST` of 192.168.50.163, which is the value of `DB_HOST_1`. So `DB_HOST` should actually be set to `DB_HOST_1` and not `DB_HOST_3`._

Cross-reference this with the cached configuration file.
```bash
docker exec -it porta bash -c "cat bootstrap/cache/config.php | grep -B 2 \"'default' => 'mysq\""
```
This will result in something like:
```php
    'database' => 
  array (
    'default' => 'mysql-3',
```

The value of `'default' =>` should match the primary connection name listed in the database status dashboard.


### General Database Connection Check
```bash
docker exec -it porta bash -c "php artisan health:single-check 'db' --do-not-store-results --no-notification --fail-command-on-failing-check"
```

### Cache a configuration change
You'd need to run this after changing the .env file, which itself should only be changed with the guidance of a Disguise engineer.
```bash
docker exec -it porta bash -c "php artisan config:cache"
```

### Check the Database Members
View all:
```bash
docker exec -it porta bash -c "php artisan health:single-check 'MysqlClusterCheck' --do-not-store-results --no-notification --fail-command-on-failing-check"
```

View this member:
```bash
docker exec -it porta bash -c "php artisan health:single-check 'MysqlNodeStatusCheck' --do-not-store-results --no-notification --fail-command-on-failing-check"
```


### Manually Create a Database Backup
#### Using the Backup Tool (Recommended)
The resulting backup will be located in the `porta` container /var/www/storage/app/Porta-Backups directory, with a name like `machine_type-machine-connection_name-backup-Y-m-d-H-i-s.zip`
```bash
docker exec -it porta bash -c "php artisan backup:run --only-db --disable-notifications"
```

#### Using the `mysqldump` command
The resulting backup will be located in the `porta-db` container root directory, with a name like `MANUAL-porta-backup-Y-m-d-H-i-s.sql`

### Restoring a Database Backup
See [Restoring a Database Backup](restoring-a-database-backup.html)

### View failed jobs
```bash
docker exec -it porta bash -c "php artisan queue:failed"
```


### Rebuild the front end
You'd want to run this after changing the `frontend/.env` file.  
** REPLACE <HOST_MACHINE_ADDRESS> WITH THE MACHINE IP **
```
REACT_APP_BASE_URL='http://<HOST_MACHINE_ADDRESS>:8000' npm --prefix frontend run build
```

### Verify PHP config (php.ini) location
```bash
docker exec -it porta bash -c 'php -r "print_r(phpinfo());" | grep php.ini'
```

### Find PHP config by partial string
**NOTE**: The config for CLI may be different from the web server config. Setup `<?php phpinfo(); ?>` in a place to access from the browser to verify the web server config.
Replace `<string>` with the string you are searching for
```bash
docker exec -it porta bash -c 'php -r "print_r(phpinfo());" | grep <string>'
```

### Viewing Server Workers
Such as those that process the play group sequence (`porta-horizon-worker`) or run database checks in the background (`porta-task-worker`)
```bash
docker exec -it porta bash -c "supervisorctl status"
```
Example result:
```bash
nginx                                    RUNNING   pid 7, uptime 0:16:57
php-fpm                                  RUNNING   pid 8, uptime 0:16:57
porta-task-worker:porta-task-worker_00   RUNNING   pid 746, uptime 0:02:57
porta-task-worker:porta-task-worker_01   RUNNING   pid 747, uptime 0:02:57
porta-horizon-worker:porta-horizon-worker_00             RUNNING   pid 732, uptime 0:02:59
porta-horizon-worker:porta-horizon-worker_01             RUNNING   pid 733, uptime 0:02:59
```

---


# Porta Database Container
> **Note**: The database container name will change depending on which machine you are accessing.
- Container name: `porta-db`
- Ports: 3306, 3307, 3308, 33060, 33061, 33062, 33063

## CLI Access
```bash
docker exec -it porta-db bash
```

_**NOTE**: Remember to replace container names (`<container-name>`) with the correct name for the machine you are running the command on, i.e., `porta-db-3` for the database on the arbiter machine._

## Configuration Files
- Configuration file: `/etc/my.cnf`
- Data directory (for advanced system administration): `/var/lib/mysql`

## Logs
Access container's general logs by clicking on its name and viewing its `Logs` menu in the Docker Dashboard.

You can also access the logs from the WSL CLI with the following command (replace `<container-name>` with the name of the container you are accessing, i.e., `docker logs porta-db-2`):
```bash
docker logs <container-name>
```


### Commands
Access the docker container and keep the MySQL CLI open (you will be prompted for the mysql root password) so that you may run SQL commands:
- _Replace `<container-name>` with the name of the container you are accessing, i.e., `porta-db`_
```bash
# Open the MySQL CLI
docker exec -it <container-name> mysql -uroot -p
```
```sql
# View replication status of the machines in the group
SELECT * FROM performance_schema.replication_group_members;
```

Or access the container and run SQL without keeping the CLI open (you will still be prompted for the mysql root password):
```bash
# Open the MySQL CLI and view replication status of the machines in the group, then close the MySQL CLI
docker exec -it <container-name> mysql -uroot -p -e "SELECT * FROM performance_schema.replication_group_members;"
```

**_For ease of use, `<container-name>` will be filled in with `porta-db` in the examples, however this should be replaced with the name of the container you are accessing, i.e., `porta-db-3` on the arbiter machine._**

#### View Replication Members
```bash
docker exec -it porta-db mysql -uroot -p -e "SELECT * FROM performance_schema.replication_group_members;"
```

#### View Installed Plugins
```bash
docker exec -it porta-db mysql -uroot -p -e "SHOW PLUGINS;"
``` 

#### View A List of Available Replication-Related System Variables
```bash
docker exec -it porta-db mysql -uroot -p -e "SHOW GLOBAL VARIABLES WHERE variable_name LIKE 'group_repl%' OR variable_name = 'server_id' OR variable_name;"
```

##### View Specific Replication-Related System Variable
Replace `<variable_name>` with the name of the variable you would like to view
```bash
docker exec -it porta-db mysql -uroot -p -e "SELECT @@global.<variable_name>;"
```
```bash
# Example: 
docker exec -it porta-db mysql -uroot -p -e "SELECT @@global.group_replication_exit_state_action;"
```

#### Start Group Replication
```bash
docker exec -it porta-db mysql -uroot -p -e "START GROUP_REPLICATION;"
```

#### Stop Group Replication
```bash
docker exec -it porta-db mysql -uroot -p -e "STOP GROUP_REPLICATION;"
```

#### View MySQL Users
```bash
docker exec -it porta-db mysql -uroot -p -e "SELECT user, host FROM mysql.user ORDER BY user;"
```

#### View Last GTID Executed
```bash
docker exec -it porta-db mysql -uroot -p -e "SHOW GLOBAL VARIABLES LIKE 'gtid_executed';"
# OR
docker exec -it porta-db mysql -uroot -p -e "SELECT @@global.gtid_executed;"
```

#### View Binlog Events
```bash
docker exec -it porta-db mysql -uroot -p -e "SHOW BINLOG EVENTS;"
```

#### View Transaction History

Details of the error and the last successfully applied transaction are recorded in the Performance Schema table `replication_applier_status_by_worker`.

```bash
docker exec -it porta-db mysql -uroot -p -e "select * from performance_schema.replication_applier_status_by_worker\G"
```


---

# Porta Socket Server Container
- Container name: `porta-socket`
- Ports: 6001

## CLI Access
```bash
docker exec -it porta-socket bash
```

## Files
- Source files: `/usr/src/app`
- Environment file: `/usr/src/app/.env`

## Logs
- General logs: `/tmp/porta-socket-server/porta-socket-server.log`
- Error logs: `/tmp/porta-socket-server/porta-socket-server-error.log`

Access container's general logs with its `Logs` menu in the Docker Dashboard

Normal `Logs` menu content on startup will look similar to:
```
info: server starting {"service":"porta-socket-server","timestamp":"2023-10-16 14:41:05"}
redis: Starting socketIO with REDIS adapter {"service":"porta-socket-server","timestamp":"2023-10-16 14:41:05"}
redis: REDIS --> redis://192.168.50.163:6379 {"service":"porta-socket-server","timestamp":"2023-10-16 14:41:05"}
info: Starting namespace: /unreal {"service":"porta-socket-server","timestamp":"2023-10-16 14:41:05"}
info: Starting namespace: /disguise {"service":"porta-socket-server","timestamp":"2023-10-16 14:41:05"}
info: Starting namespace: /playout_status {"service":"porta-socket-server","timestamp":"2023-10-16 14:41:05"}
debug: server listening {"service":"porta-socket-server","timestamp":"2023-10-16 14:41:05"}
redis: -> SUBSCRIBED {"service":"porta-socket-server","timestamp":"2023-10-16 14:41:05"}
```

_Has no custom log locations to access_

## Commands
First, access the docker container CLI before running any of the following commands:
```bash
docker exec -it porta-socket bash
```

TBD

---

# Porta Redis Container
- Container name: `porta-redis`

## CLI Access
```bash
docker exec -it porta-redis sh
```

## Files
_Has no custom files to access_

## Logs
Normal `Logs` menu content on startup will look similar to:
```
oO0OoO0OoO0Oo Redis is starting oO0OoO0OoO0Oo
* Redis version=7.2.1, bits=64, commit=00000000, modified=0, pid=1, just started
# Warning: no config file specified, using the default config. In order to specify a config file use redis-server /path/to/redis.conf
* monotonic clock: POSIX clock_gettime
* Running mode=standalone, port=6379.
* Server initialized
* Loading RDB produced by version 7.2.1
* RDB age 4145 seconds
* RDB memory usage when created 0.88 Mb
* Done loading RDB, keys loaded: 0, keys expired: 0.
* DB loaded from disk: 0.000 seconds
* Ready to accept connections tcp
```

## Commands
First, access the docker container CLI before running any of the following commands:
```bash
docker exec -it porta-redis sh
```
Next, access the Redis CLI to run any of the following commands:
```
redis-cli
```

### Monitor Redis
```
MONITOR
```

### Monitor Redis Messages from Porta API
```
PSUBSCRIBE *-API-msg
```


