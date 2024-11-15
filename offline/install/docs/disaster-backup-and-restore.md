
# Backups 

## Overview

Every 3 hours, a backup of the Porta database is created on a non-primary machine (likely the Arbiter) and stored in the `storage/app/Porta-Backups` directory of the `porta` container. The backup is a ZIP archive containing an SQL dump of the database. 

_**NOTE**: The purpose of these files is to be used for disaster recovery in the event of catastrophic data loss, not for day-to-day use._

The backup archive name is in the format `machine_type-machine-connection_name-backup-Y-m-d-H-i-s.zip` where `Y-m-d-H-i-s` is the date and time the backup was created.

## QuickRef

For more useful commands and info on checking the database status, see [Support Cheatsheet](support-cheatsheet.html) in the "Porta Database" section.

_**NOTE**: Replace `<container-name>` (and remove the `<` `>`) with the name of the container you are accessing, i.e., `porta-db-2`_

- main machine database has container name `porta-db` and port `3306` (replication port `33061`)
- backup machine database has container name `porta-db-2` and port `3307` (replication port `33062`)
- arbiter machine database has container name `porta-db-3` and port `3308` (replication port `33063`)

### View database logs
```bash
docker logs <container-name>
```

### View database replication group members
```bash
docker exec -it <container-name> mysql -uroot -p -e "SELECT * FROM performance_schema.replication_group_members;"
```

#### Start Group Replication
```bash
docker exec -it <container-name> mysql -uroot -p -e "START GROUP_REPLICATION;"
```

#### Stop Group Replication
```bash
docker exec -it <container-name> mysql -uroot -p -e "STOP GROUP_REPLICATION;"
```

## Manually Creating a Backup

### Using the Backup Tool (Recommended)
The resulting backup will be located in the `porta` container /var/www/storage/app/Porta-Backups directory, with a name like `machine_type-machine-connection_name-backup-Y-m-d-H-i-s.zip`
```bash
docker exec -it porta bash -c "php artisan backup:run --only-db --disable-notifications"
```

### Using the `mysqldump` command
The resulting backup will be located in the `porta-db` container root directory, with a name like `MANUAL-porta-backup-Y-m-d-H-i-s.sql`
- enter the database user's password when prompted
- replace the `<container_name>` and `<container_port>` with the appropriate values:
    - main machine database has container name `porta-db` and port `3306`
    - backup machine database has container name `porta-db-2` and port `3307`
    - arbiter machine database has container name `porta-db-3` and port `3308`
```bash
docker exec -it porta-db bash -c "mysqldump -h <container_name> -P <container_port> -uporta -p --single-transaction --no-tablespaces porta > MANUAL-porta-backup-$(date '+%Y-%m-%d-%H-%M-%S').sql"
```

# Recover from Data Loss

### Single Node Disaster Recovery (Data Loss)

If only a single node needs to be restored, the broken database should be wiped, rebuilt, rejoin as a new node, and let replication restore it. No need for using backups to recover. See the "Resetting a Database Container" section below.

Once the database container reset has completed, the database should join the existing group and begin replicating data from the existing databases. Once the replicating has finished, the restoration is complete.

### Multiple Node Disaster Recovery (Data Loss)

In most cases, in the event that disaster recovery is needed, most or all databases will be down or offline.

If any database containers on any machines are down, start them so that we can inspect their data and run the recovery steps. Group replication will fail on start but that's ok; we just need the database to be running in order to access it.

1. Check each machine for the most recent backup among them by running the following command. Note which machine and filename, as this is likely the backup we will want to use when restoring.
```bash
docker exec porta find "/var/www/storage/app/Porta-Backups" -maxdepth 1 -type f -name "*.zip" -exec basename {} \; | sort -r | head -n 10
```
2. If possible, manually run the backup tool on each machine
   - See the "Manually Creating a Backup" section above
3. Stop each machine's database container
4. On the machine that contains the backup you would like to use to restore, reset the database container
    - See the "Resetting a Database Container" section below
5. Once the database container reset has completed, the container should be running so that you may now run the restore tool and choose the file you identified in step 1.
    - See the "Restoring from a backup made with the backup tool" section below
6. Once the restore has completed, reset the database containers on the other machines
    - See the "Resetting a Database Container" section below
7. Once the database container reset has completed, each machine's database should join the existing group and begin replicating data from the restored database
8. Once the database containers have joined the group and finished replicating data, the restoration is complete.


## Resetting a Database Container
1. Open cmd.exe
2. Run the command `wsl`
3. Using the `cd` command, navigate to the WSL directory containing the porta on prem installation files
    - i.e., `cd ~/px/porta/porta-onprem-2.x.x+XXXXX`
4. Run the command `sudo chown -R $USER:$USER . && chmod ug+x debug/debug-repl-change.sh && debug/debug-repl-change.sh` to run the database reset tool
5. Follow the prompts displayed in the terminal to reset the database container

## Restoring from a Backup Made with the Backup Tool
1. Open cmd.exe
2. Run the command `wsl`
3. Using the `cd` command, navigate to the WSL directory containing the porta on prem installation files
   - i.e., `cd ~/px/porta/porta-onprem-2.x.x+XXXXX`
4. Run the command `sudo chown -R $USER:$USER . && chmod ug+x backups/recovery.sh && backups/recovery.sh` to run the recovery tool
5. You will eventually be prompted to choose a backup file to restore with. Enter the number corresponding to the backup you would like to restore and press enter

## Restoring from a Backup Made with the `mysqldump` Command
1. Open cmd.exe
2. Run the command `wsl`
3. Run the command `docker exec -it <container_name> bash -c "mysql -uporta -p -h <container_name> -P <container_port> porta < <backup_file>"`
   - enter the database user's password when prompted
   - replace the `<container_name>`, `<container_port>`, and `<backup_file>` with the appropriate values:
     - main machine database has container name `porta-db` and port 3306
     - backup machine database has container name `porta-db-2` and port 3307
     - arbiter machine database has container name `porta-db-3` and port 3308
     - backup file is in the format `MANUAL-porta-backup-Y-m-d-H-i-s.sql` and will vary based on the date and time the backup was created
