# Troubleshooting

## Docker Image Build Errors
If you encounter errors like:
- `ERROR: failed to calculate checksum of ref...`
- `lstat /var/lib/docker/tmp/buildkit-mount754941422/offline/docker/config: no such file or directory`

then double-check the paths inside the Dockerfiles -- the `/offline/docker` path should be `offline/build/docker`

## .env file has wrong permissions
Access API container shell as root, fix the .env file permissions, exit root shell
```bash
docker exec -u 0 -it porta bash && chown -R www-data:www-data .env && exit
```

## sed: -e expression #1, char 28: unknown option to `s'
`sed` command might need a different delimiter, for example switching from `/` to `|` delimited because the `/` delimiters interfere with the valid search characters of the URL:
BAD:
```
docker exec -it porta sh -c "sed -i 's/APP_URL=.*/APP_URL=http://$host_machine:$api_port/' .env; sed -i 's/FRONT_URL=.*/FRONT_URL=http://$host_machine:$front_port/' .env"
```
BETTER:
```
docker exec -it porta sh -c "sed -i 's|APP_URL=.*|APP_URL=http://$host_machine:$api_port/' .env; sed -i 's|FRONT_URL=.*|FRONT_URL=http://$host_machine:$front_port|' .env"
```

## 0: command not found
Reason varies with context, but an `if` statement will need to be an explicit conditional expression and not just checking a variable.
WRONG:
```
local should_seed="${2:-0}" # bash boolean --> 0 is `true`, 1 is `false`

if $should_seed; then
    # migrate DB & seed
else
    # migrate DB only
fi
```
RIGHT:
```
local should_seed="${2:-0}" # bash boolean --> 0 is `true`, 1 is `false`

if [ "$should_seed" -eq 0 ]; then
    # migrate DB & seed
else
    # migrate DB only
fi
```

## There is no existing directory at "/var/www/storage/logs" and it could not be created: Permission denied
- Access porta container
```bash
docker exec -it porta bash
```
- Check that /var/www/storage is owned by `www-data:www-data`
```bash
ls -al /var/www | grep storage
```
Should look like:
`drwxr-xr-x www-data www-data` 
If it’s not:
In another window, access API container shell as root
```bash
docker exec -u 0 -it porta bash
```
fix the .env file permissions & exit root shell
```bash
chown -R www-data:www-data storage && exit
```

From the original window, create the logs dir
```bash
mkdir /var/www/storage/logs
```

## Please provide a valid cache path.
Ensure the following directories exist:
```bash
mkdir /var/www/storage/framework && mkdir /var/www/storage/framework/sessions && mkdir /var/www/storage/framework/views && mkdir /var/www/storage/framework/cache
```

## The command 'docker' could not be found in this WSL 2 distro
Make sure Ubuntu 22.04 is set as your default Linux distro: [Porta Local Development Setup | Set default distro](https://d3technologies.atlassian.net/wiki/spaces/PORTA/pages/1714815165/Porta+Local+Development+Setup#Set-default-distro) 


## SQLSTATE[HY000] [2002] No such file or directory (SQL: select * from information_schema.tables where table_schema = porta...
Check the .env file and make sure the `DB_HOST_#` field is set. If it isn't, did you forget to set `DB_REPLICATION_ENABLED=true` in `offline/utils/vars.sh`? 
