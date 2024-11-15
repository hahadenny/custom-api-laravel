#!/bin/bash

# Create network
docker network create --driver bridge pato-net

# create primary container
- custom volume/datadir
- custom MYSQL_ROOT_PASSWORD
- update ubuntu
    - apt update
    - set timezone
    - install tools
- create sudo user
- Enable unix passwordless authentication for the the root user and secure your installation with the procedure mysql_secure_installation:
      root@e73e6f0a6f9a:/# mysql_secure_installation
      Enter current password for root (enter for none):

      Switch to unix_socket authentication [Y/n] Y
      Change the root password? [Y/n] n

      Remove anonymous users? [Y/n] Y
      Disallow root login remotely? [Y/n] Y
      Remove test database and access to it? [Y/n] Y
      Reload privilege tables now? [Y/n] Y

- connect to DB
    - create "sudo" user
        MariaDB [mysql]> CREATE USER 'pato'@'localhost' IDENTIFIED VIA unix_socket;
        MariaDB [mysql]> GRANT ALL PRIVILEGES ON *.* TO 'pato'@'localhost' WITH GRANT OPTION;
    - create remote user that can connect from any host @'%':
        MariaDB [mysql]> GRANT ALL PRIVILEGES ON *.* TO 'remoto'@'%' IDENTIFIED BY '********';
    - Create backup user with proper permissions:
      MariaDB [mysql]> GRANT RELOAD, PROCESS, LOCK TABLES, REPLICATION CLIENT ON *.* TO respalda@localhost IDENTIFIED BY '********';
    - FLUSH PRIVILEGES;
    - create DB backup
        mysqldump --all-databases > initial-db.sql
- stop container
- build image from container (?)


