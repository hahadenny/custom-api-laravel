
# Database Backup

## For Testing
- Install Porta on all machines
- Restart main DB to elect different primary
- check events & GTID
  - `SHOW BINLOG EVENTS;`
  - `SELECT @@global.gtid_executed;`
- Run command from CLI: `mysqldump -uporta -p --single-transaction --no-tablespaces --set-gtid-purged=ON porta > onprem-porta-nonroot-backup-gtid-purged-ON-read-only.sql`
- OR Run command from Laravel `php artisan backup:run --only-db --disable-notifications`
- make sure dump file is appropriate size
  - can check for `INSERT INTO` statements
    - `cat onprem-porta-nonroot-backup-gtid-purged-ON-read-only.sql | grep "INSERT INTO"`

- view backups in date order: 
  - `ls -halt storage/app/Porta-Backups`
- view most recent backup: 
  - `ls -Art /var/www/storage/app/Porta-Backups | tail -n 1`
    - i.e., `main-machine-mysql-backup-2024-01-22-21-04-52.zip`
    - ensure we only get the most recent ZIP file (in case db-dumps is also in the directory)
      - `docker exec porta find "/var/www/storage/app/Porta-Backups" -maxdepth 1 -type f -name "*.zip" | sort | tail -n 1`


# Database Restore

(Gzipped)
project root / storage / app / APP_NAME-Backups / APP_MACHINE_TYPE-DB_CONNECTION-backup-Y-m-d-H-i-s.zip -> db-dumps / DB_CONNECTION-DB_DATABASE.sql.gz

project root / storage / app / Porta-Backups / main-mysql-backup-2024-01-18-19-22-35.zip -> db-dumps / mysql-porta.sql.gz

**TODO:** The backup file needs a mysql client to restore it. There is a mysql client in `porta-db` but not `porta`, so we need to move the backup file to `porta-db` to restore it.

1. Open cmd.exe
2. Enter `wsl`
3. Enter:
    - ?? should this use mounted dir `/tmp/storage/app` or `docker cp` from porta container -> host -> db container ??
   local tester:
    - `BACKUP_FILE=$(docker exec 2fc77f36d369e54cfae9e237ecc12edbde6dfcda0176aefe69daf950104c1220 ls -Art /var/www/html/storage/app/Porta-Backups | tail -n 1) && \
     docker cp -a "2fc77f36d369e54cfae9e237ecc12edbde6dfcda0176aefe69daf950104c1220:/var/www/html/storage/app/Porta-Backups/$BACKUP_FILE" ~/ && \
      docker cp -a ~/"$BACKUP_FILE" porta-db:/ && \
      rm -rf ~/"$BACKUP_FILE"
      `
    actual paths:
    - `BACKUP_FILE=$(docker exec porta ls -Art /var/www/storage/app/Porta-Backups | tail -n 1) && \
      docker cp -a "porta:/var/www/storage/app/Porta-Backups/$BACKUP_FILE" ~/ && \
      docker cp -a ~/"$BACKUP_FILE" porta-db:/ && \
      rm -rf ~/"$BACKUP_FILE" `

5. Enter `docker exec -it porta-db bash -c "unzip $BACKUP_FILE && gunzip < db_dumps/mysql-porta.sql.gz | mysql -uroot -p -h porta-db -P 3306"`
6. ``

```bash
unzip /var/www/html/storage/app/Porta-Backups/main-mysql-backup-2024-01-18-19-39-28.zip

cd /var/www/html/storage/app/Porta-Backups/db_dumps

# gunzip < mysql-porta.sql.gz | mysql -uusername -p -h hostname -P port databasename

# backups of `--all-databases` don't need to specify `databasename` to restore
gunzip < /var/www/html/storage/app/Porta-Backups/db_dumps/mysql-porta.sql.gz | mysql -uroot -p -h porta-db -P port
```

