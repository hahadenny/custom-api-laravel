
# Contents
- [Troubleshooting Installation/Update](#troubleshooting)
  - [Database Replication](#database-replication)
  - [Installation Aborts because `Container 'porta-socket' did not become ready within the timeout.`](#installation-aborts-because-container-porta-socket-did-not-become-ready-within-the-timeout)
  - [Porta Version listed in the `About` section does not appear to have changed](#porta-version-listed-in-the-about-section-does-not-appear-to-have-changed)
  - [`SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry`](#sqlstate23000-integrity-constraint-violation-1062-duplicate-entry-superadmindisguiseone-for-key-usersusers_email_unique)
  - [**Illuminate\Database\QueryException - SQLSTATE[HY000] [2002] Connection refused**](#illuminatedatabasequeryexception---sqlstatehy000-2002-connection-refused)
  - [The command 'docker' could not be found in this WSL 2 distro](#the-command-docker-could-not-be-found-in-this-wsl-2-distro)
  - [Warning: Redis connection could not be made to 'redis://localhost:6379' : Error: connect ECONNREFUSED](#warning-redis-connection-could-not-be-made-to-redislocalhost6379--error-connect-econnrefused)
  - [`line 2: $'\r': command not found` or `/bin/bash^M: bad interpreter: No such file or directory`](#line-2-r-command-not-found-or-binbashm-bad-interpreter-no-such-file-or-directory)
  - [ERROR 3093 (HY000) at line 1: The group_replication_group_name cannot be changed when Group Replication is running with the read only option](#error-3093-hy000-at-line-1-the-group_replication_group_name-cannot-be-changed-when-group-replication-is-running-with-the-read-only-option)
  - [SQLSTATE[HY000]: General error: 1290 The MySQL server is running with the --read-only option](#sqlstatehy000-general-error-1290-the-mysql-server-is-running-with-the---read-only-option)
  - [When running backup tool command: `Backup failed because Parameter 'host' cannot be empty...`](#when-running-backup-tool-command-backup-failed-because-parameter-host-cannot-be-empty)
  - [Error on Login: "500 Internal Server Error" / "Could not create token: Key provided is shorter than 256 bits"](#error-on-login-500-internal-server-error--could-not-create-token-key-provided-is-shorter-than-256-bits)

# Installation/Update Troubleshooting

## Database Replication
For database troubleshooting, see [group-replication-troubleshooting.html](docs/group-replication-troubleshooting.html)


## Installation Aborts because `Container 'porta-socket' did not become ready within the timeout.`
This error can occur when the socket server container is not able to start up in time, without being caused by any particular errors. Re-run the install/update script and it should complete successfully.


## Porta Version listed in the `About` section does not appear to have changed
It could be that the browser is caching the old version. Try clearing the browser cache and refreshing the page. In Chrome:
- Click on the browser address bar (Porta disables some default keyboard shortcuts in the browser)
- Hit Ctrl+Shift+Delete
- Under the basic tab,
    - select "All time" for the time range
    - check the box for "Cookies and other site data"
- Click "Clear data"
- Refresh the page

To check against the version registered in the Porta backend, run the following command in the WSL terminal:
```bash
docker exec -i porta sh -c "php artisan check:version"
```

If this version is correct, then the update was successful and the browser is just caching the old version information.

## `SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'superadmin@disguise.one' for key 'users.users_email_unique'`
This error usually occurs when the install script is run more than once on the same machine. Operation should not be affected and you may continue the installation steps as normal.

## **Illuminate\Database\QueryException - SQLSTATE[HY000] [2002] Connection refused**
Sometimes the database is not finished running/configuring before the install script tries to make changes to it. In this case, just run the following commands in the WSL terminal:

**For first time installs**: set the config and initialize the database & Run data insert:
```bash
docker exec -it porta bash -c "php artisan config:cache && yes | php artisan migrate --seed"
```

**For updates**: set the config and initialize the database:
```bash
docker exec -it porta bash -c "php artisan config:cache && yes | php artisan migrate"
```

## The command 'docker' could not be found in this WSL 2 distro
If you see this error along with 'ERROR: Error occurred in command: 'docker load -i "$PORTA_IMAGES_TAR"' (Exit Code: 1) at line 39':
- Make sure Docker Desktop is running; you should be able to see the Docker icon in the taskbar or system tray
    - If it appears to be running, it could be in resource-saver mode. In the system tray, right-click the Docker icon and select "restart", then try again

If the above does not work, make sure Ubuntu 22.04 is set as your default Linux distro: [Porta Local Development Setup | Set default distro](https://d3technologies.atlassian.net/wiki/spaces/PORTA/pages/1714815165/Porta+Local+Development+Setup#Set-default-distro)

## Warning: Redis connection could not be made to 'redis://localhost:6379' : Error: connect ECONNREFUSED 127.0.0.1:6379
1. Stop the Socket Server container
2. Ensure the Redis container is started (start or restart it)
3. Once the Redis container is running, start the Socket Server container

## `line 2: $'\r': command not found` or `/bin/bash^M: bad interpreter: No such file or directory`
This is caused by windows line endings in the bash scripts. Run the following command in the porta/porta-onprem directory in WSL and try again:
```bash
find . -type f -exec sed -i 's/\r$//' {} \;
```


## ERROR 3093 (HY000) at line 1: The group_replication_group_name cannot be changed when Group Replication is running
This error can occur when running the install script on a machine that has already been set up as a Porta machine.

Simply restart the database container (using the UI or with `docker restart <container-name>`) and then view the container's logs to ensure replication starts correctly: `docker logs -f <container-name>`. You should eventually see a message like:
```
[System] [MY-011490] [Repl] Plugin group_replication reported: 'This server was declared online within the replication group.'
```

If this occurs on the main machine, you may also need to seed the database. Check to see if any users are registered:
```
docker exec -it porta-db mysql -uroot -p -e "SELECT * FROM porta.users;"
```

If this is the main machine and the results are empty, seed the database with:
```
docker exec -it porta bash -c "php artisan migrate --seed"
```

## SQLSTATE[HY000]: General error: 1290 The MySQL server is running with the --read-only option
If this error is displayed in the Porta web app, then this instance of Porta is not connected to the primary database. It's possible for this issue to occur if a database has recently gone down and Porta has not yet recovered.

Porta should automatically recover after a few seconds, however if it does not, then you can take the following steps:

Ensure that at least two machine's databases are running and check the database logs for errors.

Check logs:
```bash
docker logs <container-name>
```

View group members and make sure that:
- at least two members have a `MEMBER_STATE` of `ONLINE`
- only one member has `MEMBER_ROLE` of `PRIMARY`
- the member with `MEMBER_ROLE` of `PRIMARY` has `MEMBER_STATE` of `ONLINE`
```bash
docker exec -it <container-name> mysql -uroot -p -e "SELECT * FROM performance_schema.replication_group_members;"
```
This will result in something like:
```bash
+---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
| CHANNEL_NAME              | MEMBER_ID                            | MEMBER_HOST    | MEMBER_PORT | MEMBER_STATE | MEMBER_ROLE | MEMBER_VERSION | MEMBER_COMMUNICATION_STACK |
+---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
| group_replication_applier | 1a351ce3-ba19-11ee-9e59-0242ac120002 | 192.168.50.163 |        3306 | ONLINE       | PRIMARY   | 8.0.32         | XCom                       |
| group_replication_applier | d7c0535c-ba19-11ee-9f28-0242ac140002 | 192.168.50.20  |        3307 | ONLINE       | SECONDARY   | 8.0.32         | XCom                       |
| group_replication_applier | e4a0ac63-ba19-11ee-9f54-0242ac150002 | 192.168.50.42  |        3308 | ONLINE       | SECONDARY     | 8.0.32         | XCom                       |
+---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
```

If everything looks good, see if Porta is detecting the primary connection correctly on the problem machine:
```bash
docker exec -it porta bash -c "php artisan health:single-check 'PrimaryDatabaseConnectionCheck' --do-not-store-results --no-notification --fail-command-on-failing-check"
```
This will result in something like:
```bash
Running health check...

Running check: Primary Database Connection...
Ok: Found online primary: {"member_host":"192.168.50.163", member_port":3306}

All done!
```

If the above command returns `Ok: Found online primary...` and the `member_host` displayed by the command matches the `MEMBER_HOST` from the database command above, then the issue is likely that Porta's active connection is not being set to the primary connection.

To check Porta's active connection, run the following command:
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

The `DB_HOST` variable should be set to the primary database's `DB_HOST_#` variable.

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

The numeric suffix of the value of `'default' =>` should match the suffix of the `DB_HOST_#` variable that `DB_HOST` is set to.

_In this example, they're both 3, so the configuration is cached correctly, but is not set to the primary connection_

Your initial run of `docker exec -it porta bash -c "php artisan health:single-check 'PrimaryDatabaseConnectionCheck' --do-not-store-results --no-notification --fail-command-on-failing-check"` should have triggered a connection change to the primary database.

If it did not, then you can manually set the connection to the primary database by editing the .env file's `DB_HOST` variable and then running `php artisan config:cache` to cache the new configuration.

## When running backup tool command: `Backup failed because Parameter 'host' cannot be empty...`

This error can occur when running the backup tool command on a machine that does not have its configuration cached.

To fix this, run the following command:
```bash
docker exec -it porta bash -c "php artisan config:cache"
```

Running the backup tool command again should now work.


## Error on Login: "500 Internal Server Error" / "Could not create token: Key provided is shorter than 256 bits"
If you get this generic error, you may need to cache a new `JWT_SECRET`

Try the following steps:

1. Access the docker CLI as described in the Docker CLI section
2. Run `php artisan cache:clear` to clear your cache 
3. Run `php artisan jwt:secret` to generate a token key, confirm with yes
4. Run `php artisan config:cache` to cache the token key so your config can use it

Then try logging in to Porta again
