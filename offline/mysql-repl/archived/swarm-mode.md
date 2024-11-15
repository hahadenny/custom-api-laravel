

# !! NOTE !! This ended up not being necessary but is saved for historical/doc purposes

# Swarm / Overlay Network
> https://oscarchou.com/posts/troubleshoot/fail-join-swarm-manager-node-in-wsl

Containers need to communicate directly, so need overlay network (swarm mode)

## Option 1
1. check that the port 2378 is not being used or excluded
```bash
# check if using port
netstat -tn | grep 2378

# check if excluding port
netsh int ipv4 show excludedportrange protocol=tcp | grep 2378
```

Windows view tcp connections: `netsh interface ipv4 show tcpconnections`

2. in WINDOWS powershell **AS ADMIN** create port forward so that backup machine can access docker subnet
```bash
netsh interface portproxy add v4tov4 listenport=2378 listenaddress=0.0.0.0 connectport=2378 connectaddress=<PRIMARY's WSL IP>
```
> This command creates a port proxy to forward incoming traffic on port 2378 of the Windows host to port 2378 of the WSL Ubuntu distro

View this: `netsh interface portproxy show v4tov4`

Remove this: `netsh interface portproxy delete v4tov4 listenport=2378 listenaddress=0.0.0.0`

3. In WSL on primary, create a container “swarm-manager-proxy” to be the proxy.
   This command:
- creates a new container with the name "swarm-manager-proxy" using the `alpine/socat` image,
- that listens on port `2378` of the host machine, and
- forwards any incoming traffic to port `2377` on IP address `192.168.65.3` (docker subnet IP)

DID NOT WORK:
`docker: Error response from daemon: driver failed programming external connectivity on endpoint swarm-manager-proxy (9e783549c65b5d4adae4851e4417514e02d5bdca7854669ba676674b77f20790): listen tcp4 192.168.50.163:2378: bind: cannot assign requested address.`
```bash
docker run --rm -d -p 2378:2378 --name swarm-manager-proxy alpine/socat tcp-l:2378,fork,reuseaddr tcp:192.168.65.3:2377
```
> It runs in detached mode and will be removed automatically when it exits. This command did final port forward to docker swarm interface in docker subnet

4. On backup machine, run the join swarm command, **using the primary's windows host IP** instead of the docker subnet IP. Also use the port we've setup forwarding for
```bash
docker swarm join --token SWMTKN-1-1c0ih56l2etekc6m4ymv7hqltala6wyxo2fmxez07m5rrr37rz-23v1bn533dzv1dxtuv2hl4h4w <PRIMARY MACHNINE IP>:2378
```

## Option 2

1. check that the port 2378 is not being used or excluded
```bash
# check if using port
netstat -tn | grep 2378

# check if excluding port
netsh int ipv4 show excludedportrange protocol=tcp | grep 2378
```

2. In WSL on primary, create a container “swarm-manager-proxy” to be the proxy.
   This command:
- creates a new container with the name "swarm-manager-proxy" using the `alpine/socat` image,
- that listens on port `2378` of the host machine, and
- forwards any incoming traffic to port `2377` on IP address `192.168.65.3` (docker subnet IP)
  **- by using the IP 0.0.0.0, it makes the port accessible from any IP address, not just the localhost**

```bash
docker run --rm -d -p 0.0.0.0:2378:2378 --name swarm-manager-proxy alpine/socat tcp-l:2378,fork,reuseaddr tcp:192.168.65.3:2377
```

3. On the backup machine, run the join swarm command, **using the primary's windows host IP** instead of the docker subnet IP. Also use the port we've setup forwarding for
```bash
docker swarm join --token SWMTKN-1-1c0ih56l2etekc6m4ymv7hqltala6wyxo2fmxez07m5rrr37rz-23v1bn533dzv1dxtuv2hl4h4w <PRIMARY MACHNINE IP>:2378
```

RESULT:
```
Error response from daemon: Timeout was reached before node joined. The attempt to join the swarm will continue in the background. Use the "docker info" command to see the current swarm status of your node.
```

Also tried with #2 and #3 run on the backup machine

Then tried adding docker config:
_(file in `C:\Users\jessd\.docker`)_
```
"hosts": [
    "unix:///var/run/docker.sock",
    "tcp://127.0.0.1:2375"
  ],
```
And then joining swarm -- did not work

Tried:
```
"hosts": [
    "unix:///var/run/docker.sock",
    "tcp://127.0.0.1:2378"
  ],
```
And then joining swarm -- did not work

---

```
$ docker info

Client: Docker Engine - Community
 Version:    24.0.6
 Context:    default
 Debug Mode: false
 Plugins:
  buildx: Docker Buildx (Docker Inc.)
    Version:  v0.11.2-desktop.5
    Path:     /usr/local/lib/docker/cli-plugins/docker-buildx
  compose: Docker Compose (Docker Inc.)
    Version:  v2.22.0-desktop.2
    Path:     /usr/local/lib/docker/cli-plugins/docker-compose
  dev: Docker Dev Environments (Docker Inc.)
    Version:  v0.1.0
    Path:     /usr/local/lib/docker/cli-plugins/docker-dev
  extension: Manages Docker extensions (Docker Inc.)
    Version:  v0.2.20
    Path:     /usr/local/lib/docker/cli-plugins/docker-extension
  init: Creates Docker-related starter files for your project (Docker Inc.)
    Version:  v0.1.0-beta.8
    Path:     /usr/local/lib/docker/cli-plugins/docker-init
  sbom: View the packaged-based Software Bill Of Materials (SBOM) for an image (Anchore Inc.)
    Version:  0.6.0
    Path:     /usr/local/lib/docker/cli-plugins/docker-sbom
  scan: Docker Scan (Docker Inc.)
    Version:  v0.26.0
    Path:     /usr/local/lib/docker/cli-plugins/docker-scan
  scout: Docker Scout (Docker Inc.)
    Version:  v1.0.7
    Path:     /usr/local/lib/docker/cli-plugins/docker-scout

Server:
 Containers: 16
  Running: 3
  Paused: 0
  Stopped: 13
 Images: 13
 Server Version: 24.0.6
 Storage Driver: overlay2
  Backing Filesystem: extfs
  Supports d_type: true
  Using metacopy: false
  Native Overlay Diff: true
  userxattr: false
 Logging Driver: json-file
 Cgroup Driver: cgroupfs
 Cgroup Version: 1
 Plugins:
  Volume: local
  Network: bridge host ipvlan macvlan null overlay
  Log: awslogs fluentd gcplogs gelf journald json-file local logentries splunk syslog
 Swarm: error
  NodeID:
  Error: rpc error: code = DeadlineExceeded desc = context deadline exceeded
  Is Manager: false
  Node Address: 192.168.65.3
 Runtimes: io.containerd.runc.v2 runc
 Default Runtime: runc
 Init Binary: docker-init
 containerd version: 8165feabfdfe38c65b599c4993d227328c231fca
 runc version: v1.1.8-0-g82f18fe
 init version: de40ad0
 Security Options:
  seccomp
   Profile: unconfined
 Kernel Version: 5.15.90.1-microsoft-standard-WSL2
 Operating System: Docker Desktop
 OSType: linux
 Architecture: x86_64
 CPUs: 8
 Total Memory: 7.728GiB
 Name: docker-desktop
 ID: 36fcb11c-c9cd-49cb-ba88-06f1d9b3965d
 Docker Root Dir: /var/lib/docker
 Debug Mode: false
 HTTP Proxy: http.docker.internal:3128
 HTTPS Proxy: http.docker.internal:3128
 No Proxy: hubproxy.docker.internal
 Experimental: false
 Insecure Registries:
  hubproxy.docker.internal:5555
  127.0.0.0/8
 Live Restore Enabled: false

WARNING: No blkio throttle.read_bps_device support
WARNING: No blkio throttle.write_bps_device support
WARNING: No blkio throttle.read_iops_device support
WARNING: No blkio throttle.write_iops_device support
WARNING: daemon is not using the default seccomp profile
```
