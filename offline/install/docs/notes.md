

docker compose -f "./install/config/docker-compose-monitoring.yml" up -d





# Portainer
```bash
docker volume create portainer_data
```

```bash
docker run -d -p 8008:8008 -p 9443:9443 --network=porta-net --name portainer --restart=always -v /var/run/docker.sock:/var/run/docker.sock -v portainer_data:/data portainer/portainer-ce:latest
```

# Glances
https://nicolargo.github.io/glances/
https://github.com/nicolargo/glances

https://glances.readthedocs.io/en/latest/docker.html

CLI DASHBOARD:
```bash
docker run --rm -e TZ="${TZ}" -v /var/run/docker.sock:/var/run/docker.sock:ro -v /run/user/1000/podman/podman.sock:/run/user/1000/podman/podman.sock:ro --pid host --network host -it nicolargo/glances:latest-full
```

WEB:
```bash
docker run -d --network=porta-net --restart="always" -p 61208-61209:61208-61209 -e TZ="${TZ}" -e GLANCES_OPT="-w" -v /var/run/docker.sock:/var/run/docker.sock:ro -v /run/user/1000/podman/podman.sock:/run/user/1000/podman/podman.sock:ro --pid host nicolargo/glances:latest-full
```
---


https://docs.docker.com/config/daemon/prometheus/#create-a-prometheus-configuration

https://hub.docker.com/extensions/docker/resource-usage-extension?_gl=1*lzc8m7*_ga*OTk1NjA4MDkwLjE2OTgzNDIyNDQ.*_ga_XJWPQMJYHQ*MTcyMjI5NzIxNy4xMC4xLjE3MjIyOTcyMjIuNTUuMC4w

https://docs.portainer.io/start/install-ce
https://github.com/portainer/portainer


https://github.com/maheshmahadevan/docker-monitoring-windows


CONTAINER STATS:
```bash
docker stats
```


# Exporting Metrics Directly from Docker

In prometheus.yml:
```yaml
scrape_configs:
  - job_name: docker
    # metrics_path defaults to '/metrics'
    # scheme defaults to 'http'.
    static_configs:
      - targets: ["host.docker.internal:9323"]
```



Edit docker daemon.json, then EXIT AND RESTART DOCKER: 
```json
{
  "metrics-addr": "127.0.0.1:9323"
}
```


```bash
# note: DOES NOT WORK without the host IP here with the ports
docker run --name prometheus \
    --mount type=bind,source=./config/prometheus.yml,destination=/etc/prometheus/prometheus.yml \
    -p 192.168.50.163:9090:9090 \
    --add-host host.docker.internal=host-gateway \
    prom/prometheus
```

---

FOR DOCKER COMPOSE IN WINDOWS: in powershell, run `$Env:COMPOSE_CONVERT_WINDOWS_PATHS=1` before running `docker-compose up -d`



```bash
 docker run \
 --rm \
  --volume=/var/run:/var/run:rw \
  --volume=/sys:/sys:ro \
  --volume=/var/lib/docker/:/var/lib/docker:ro \
  --volume=/var/run/docker.sock:/var/run/docker.sock:rw \ 
  -p 8088:8080 \
  --detach=true \
  --name=cadvisor \
  --network=porta-net \
  google/cadvisor:latest
```
