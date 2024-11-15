
# Portainer

- https://docs.portainer.io/start/install-ce
- https://github.com/portainer/portainer

```bash
docker volume create portainer_data
```

```bash
docker run -d -p 8008:8008 -p 9443:9443 --network=porta-net --name portainer --restart=always -v /var/run/docker.sock:/var/run/docker.sock -v portainer_data:/data portainer/portainer-ce:latest
```
