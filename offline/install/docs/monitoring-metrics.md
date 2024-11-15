# Monitoring & Metrics

# Web UIs
- SocketIO Admin: `http://<MACHINE-IP>:8000/socketio-adminui`
- Portainer: https://localhost:9443/
- Glances: http://localhost:61208
- Grafana: http://localhost:3000/
- Prometheus: http://localhost:9090/
- cAdvisor: http://localhost:8088/containers/

## Glances
Find glances log file location: 
```bash
docker exec -it glances sh -c "glances -V"

# Results: /tmp/glances-root.log
```
> Note: This is the logs for the glances app itself, not the logs of the results of the monitoring. 

---

# Docker
CONTAINER STATS:
```bash
docker stats
```

## Exporting Metrics Directly from Docker

> This is already done during Porta installation. 

To export metrics for Prometheus, edit the docker daemon.json, then EXIT AND RESTART DOCKER:
```json
{
  "metrics-addr": "127.0.0.1:9323"
}
```

