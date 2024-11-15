




---

# Prod servers
- Linux: Ubuntu 20.04 (focal)
- MySQL: 8.0.33-26.14 for Linux on x86_64
- Galera: `wsrep_provider_version` 4.15(r86ced4c6) 
  - find this with `SHOW STATUS LIKE 'wsrep_%';`

# SAME FOR ALL

## wsrep_provider_options
### base_port
4567

### base_dir
/var/lib/mysql/

## wsrep_node_address
`<empty>`

## wsrep_data_home_dir
/var/lib/mysql/

---


# us-east-1 cluster node

## wsrep_sst_receive_address
the public IPv4 address of EC2 instance (174.129.91.169)

## wsrep_provider_options
### base_host
the private IPv4 address of EC2 instance (172.31.89.178)



## wsrep_data_home_dir
/var/lib/mysql/

## wsrep_cluster_address
gcomm://


---


# eu-west-3 cluster node

## wsrep_sst_receive_address
the public IPv4 address of EC2 instance -- 35.180.38.42

## wsrep_provider_options
### base_host
the private IPv4 address of EC2 instance -- 172.31.12.186

## wsrep_cluster_address
gcomm://174.129.91.169,54.215.121.18 
    --> 174.129.91.169: public IP of us-east-1
    --> 54.215.121.18: ?? the old eu-west-3 instance? (it's not London or the ohio backup)


---


# us-east-2 (OHIO) cluster node

## wsrep_sst_receive_address
the public IPv4 address of EC2 instance -- 3.17.141.175

## wsrep_provider_options
### base_host
the private IPv4 address of EC2 instance -- 172.31.16.77

## wsrep_cluster_address
gcomm://174.129.91.169,54.215.121.18,35.180.38.42
    --> 174.129.91.169: public IP of us-east-1
    --> 54.215.121.18: ?? the old eu-west-3 instance? (it's not London or the ohio backup)
    --> 35.180.38.42: public IP of eu-west-3

