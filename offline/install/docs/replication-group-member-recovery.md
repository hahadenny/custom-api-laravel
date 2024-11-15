
# Replication Group Member Recovery

## Overview

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

## Recovery

### All/Most Databases Are Down and Won't Come Online Correctly
When all databases are down and won't come online correctly, as in the case of a site power failure, we need to manually bootstrap the group.

**NOTE**: When running commands that refer to database containers be sure to use the correct **<container_name>** and **<container_port>** for the machine you are running the command on.
- main machine database has container name `porta-db` and port `3306` (replication port `33061`)
- backup machine database has container name `porta-db-2` and port `3307` (replication port `33062`)
- arbiter machine database has container name `porta-db-3` and port `3308` (replication port `33063`)

1. Stop each database container on each machine
2. Start the database container on a single machine
3. On the machine that has a database running, do the following steps: 
   1. Open cmd.exe
   2. Run the command `wsl`
   3. Using the `cd` command, navigate to the WSL directory containing the porta on prem installation files
    - i.e., `cd ~/px/porta/porta-onprem-2.x.x+XXXXX`
   4. Run the following command to bootstrap and restart group replication:
       - NOTE: Replace **<container_name>** (and remove the `<` `>`) with the correct name for machine you are running the command on
    ```bash
    docker exec -it <container_name> mysql -uporta -p -e "SET PERSIST group_replication_bootstrap_group=ON; STOP GROUP_REPLICATION;" && docker exec -it <container_name> mysql -uporta -p -e "START GROUP_REPLICATION; SET PERSIST group_replication_bootstrap_group=OFF;"
    ```
4. Once this database has come online as a primary member, start the database containers on the other machines and they should rejoin the group automatically


### Single Database Failing to Rejoin Group
1. On each machine, check the status of members in the group
   - See the "View database replication group members" section above
2. If the other machines are ONLINE and there is a member with `MEMBER_ROLE` of `PRIMARY`, then we can try simply restarting the database container on the machine that is failing to rejoin the group
3. If restarting the database container does not work, then we can try resetting the database container that is failing to rejoin the group
    - See [Group Replication Troubleshooting]("group-replication-troubleshooting.html") under the "Resetting a Database Container" section


### Every Machine Only Registers Itself in the Group
If each machine only sees itself in the replication group, this likely means that there was no existing group to join. This can happen if the machines are not able to communicate with each other, or if the group replication process was not bootstrapped on any of the machines. 

To fix this, group replication needs to be restarted **on each machine**: See [Bootstrapping Group Replication](#bootstrapping-group-replication)


### A Joining Member Creates Its Own Group
If a joining member creates its own group, this likely means that the `group_replication_bootstrap_group` setting was not set to `OFF` on the joining member. Set this to OFF and restart the database container to rejoin the existing group. 


### A Machine is Listed as UNREACHABLE
If there is a majority remaining, the member should be kicked out automatically after a timeout period of about 30 seconds. You may also restart the container to force it to rejoin the group.

If the restart does not work, group replication needs to be restarted **on each machine**: See [Bootstrapping Group Replication](#bootstrapping-group-replication)


### Misc
For more troubleshooting details and error messages, see the [Group Replication Troubleshooting](group-replication-troubleshooting.html) document. 



# Bootstrapping Group Replication
- _Replace `<container-name>` with the name of the container you are accessing, i.e., `porta-db`_

1. Stop group replication on each machine:
```bash
docker exec -it <container-name> mysql -uroot -p -e "STOP GROUP_REPLICATION;"
```
2. On one machine, turn bootstrapping on in the database:
```bash
docker exec -it <container-name> mysql -uroot -p -e "SET PERSIST group_replication_bootstrap_group=ON;"
```
3. Start group replication on the bootstrapping database only:
```bash
docker exec -it <container-name> mysql -uroot -p -e "START GROUP_REPLICATION"
```
4. Once the bootstrapped database is running and its `MEMBER_STATE` is `ONLINE`, turn bootstrapping off:
```bash
docker exec -it <container-name> mysql -uroot -p -e "SET PERSIST group_replication_bootstrap_group=OFF;"
```
5. Start group replication on the other machines:
```bash
docker exec -it <container-name> mysql -uroot -p -e "START GROUP_REPLICATION"
```
6. Once all machines are running, check the replication group members again:
```bash
docker exec -it <container-name> mysql -uroot -p -e "SELECT * FROM performance_schema.replication_group_members;"
```

All members should be running correctly and `ONLINE`.
