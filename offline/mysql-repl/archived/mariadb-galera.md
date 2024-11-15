
> [Official Docs](https://mariadb.com/kb/en/galera-cluster/)

# MariaDB Galera Cluster 

Available with MariaDB 10.1 and later

* Virtually synchronous replication
  * basically synchronous replication but not using 2-phase commits
* Active-active multi-primary topology
* Read and write to any cluster node
* Automatic membership control, failed nodes drop from the cluster
* Automatic node joining
* True parallel replication, on row level
* Direct client connections, native MariaDB look & feel

## Limitations
- only InnoDB
- explicit locking not supported
- all tables must have primary keys
- the general query log and the slow query log cannot be directed to a table
  - If you enable these logs, then you must forward the log to a file by setting `log_output=FILE`
- XA transactions are not supported
- 2GB or 128K row transaction size limit 
- Do not rely on auto-increment values to be sequential
- Do NOT change binlog_format at runtime
  - it is likely not only cause replication failure, but make all other nodes crash.
- performance of the cluster cannot be higher than performance of the slowest node
- `FLUSH PRIVILEGES` is not replicated
- the query cache needed to be disabled by setting query_cache_size=0

## BE AWARE 

- To change existing table structure: https://mariadb.com/kb/en/tips-on-converting-to-galera/#alters
- `SET GLOBAL wsrep_debug = 1;` leads to a lot of debug info in the error log
- Number of nodes per cluster:
  - https://mariadb.com/kb/en/tips-on-converting-to-galera/#how-many-nodes-to-have-in-a-cluster

## Requirements
> https://mariadb.com/kb/en/getting-started-with-mariadb-galera-cluster/#prerequisites
- swap size
  - Writeset caching during state transfer are cached in memory

---

# Install

## Docker Image
> https://hub.docker.com/r/bitnami/mariadb-galera/

docker network create app-tier --driver bridge

docker run -d --name mariadb-galera \
-e ALLOW_EMPTY_PASSWORD=yes \
--network app-tier \
bitnami/mariadb-galera:latest

- `MARIADB_GALERA_CLUSTER_BOOTSTRAP=yes`: Whether node is first node of the cluster. No defaults. 
  - ONLY SET ON THE FIRST NODE 
- `MARIADB_GALERA_CLUSTER_NAME`: Galera cluster name. Default to `galera`.
- `MARIADB_GALERA_CLUSTER_ADDRESS`: Galera cluster address to join. Defaults to `gcomm://` on a bootstrap node.
- `MARIADB_GALERA_NODE_ADDRESS`: Node address to report to the Galera cluster. Defaults to `eth0` address inside container.
  - host IP
- `MARIADB_GALERA_MARIABACKUP_USER`: mariabackup username for State Snapshot Transfer(SST). Defaults to `mariabackup`.
- `MARIADB_GALERA_MARIABACKUP_PASSWORD`: mariabackup password for SST. No defaults.
- `MARIADB_REPLICATION_USER`: mariadb replication username. Defaults to `monitor`.
- `MARIADB_REPLICATION_PASSWORD`: mariadb replication user password. Defaults to `monitor`.

### Remote connect 
If you run the MariaDB Galera nodes in isolated networks (for example, traditional Docker bridge networks on different hosts without Kubernetes), you must make sure every node knows its connectable public IP address (the IP of each host). 

You should add extra flags to `MARIADB_EXTRA_FLAGS` 
- --wsrep_provider_options=ist.recv_addr=<PUBLIC_IP>:4568;ist.recv_bind=0.0.0.0:4568 
- --wsrep_node_incoming_address=<PUBLIC_IP> 
- --wsrep_sst_receive_address=<PUBLIC_IP> 

and publish all MariaDB Galera ports to host by 
`-p 3306:3306,4444:4444,4567:4567,4568:4568` 

Another choice is using the Docker host network which makes every node can connect to each other without extra flags.
