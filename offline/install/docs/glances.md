
# Glances
- https://nicolargo.github.io/glances/
- Docker Usage: https://glances.readthedocs.io/en/latest/docker.html


## Quick Start
CLI DASHBOARD:
```bash
docker run --rm -e TZ="${TZ}" -v /var/run/docker.sock:/var/run/docker.sock:ro -v /run/user/1000/podman/podman.sock:/run/user/1000/podman/podman.sock:ro --pid host --network host -it nicolargo/glances:latest-full
```

WEB Dashboard:
```bash
docker run -d --network=porta-net \
    --restart="always" \
    -p 61208-61209:61208-61209 \
    -e TZ="${TZ}" \
    -e GLANCES_OPT="-w" \
    -v /var/run/docker.sock:/var/run/docker.sock:ro \
    --pid host \
    nicolargo/glances:latest-full
```

```bash
docker run -d --restart="always" -p 61208-61209:61208-61209 -e GLANCES_OPT="-w" -v /var/run/docker.sock:/var/run/docker.sock:ro --pid host nicolargo/glances:latest-full
```

## Export to JSON:
https://glances.readthedocs.io/en/latest/gw/json.html

## API
- https://github.com/nicolargo/glances/wiki/The-Glances-RESTFULL-JSON-API
- Locally: http://localhost:61208/docs

## Actions
> https://glances.readthedocs.io/en/latest/aoa/actions.html

Action stats list: https://github.com/nicolargo/glances/wiki/The-Glances-RESTFULL-JSON-API for the stats list.
### Alert
Get the Alert list: `curl http://localhost:61208/api/4/alert`

Fields descriptions:
- `begin`: Begin timestamp of the event (unit is timestamp)
- `end`: End timestamp of the event (or -1 if ongoing) (unit is timestamp)
- `state`: State of the event (WARNING|CRITICAL) (unit is string)
- `type`: Type of the event (CPU|LOAD|MEM) (unit is string)
- `max`: Maximum value during the event period (unit is float)
- `avg`: Average value during the event period (unit is float)
- `min`: Minimum value during the event period (unit is float)
- `sum`: Sum of the values during the event period (unit is float)
- `count`: Number of values during the event period (unit is int)
- `top`: Top 3 processes name during the event period (unit is list)
- `desc`: Description of the event (unit is string)
- `sort`: Sort key of the top processes (unit is string)
- `global_msg`: Global alert message (unit is string)

### Examples of Actions

> Use `&&` as separator for multiple commands

Execute the `foo.py` script if the last 5 minutes load are critical then add the `_action` line to the Glances configuration file:
```bash
[load]
critical=5.0
critical_action=python /path/to/foo.py
```

#### Create a log file
Create a log file containing used vs total disk space if a space trigger warning is reached:
```bash
[fs]
warning=70
warning_action=echo {{mnt_point}} {{used}}/{{size}} > /tmp/fs.alert
```
_All the stats are available in the command line through the use of the [Mustache](https://mustache.github.io/) syntax. [Chevron](https://github.com/noahmorrison/chevron) is required to render the mustache's template syntax._


Create a log file containing the total user disk space usage for a device and notify by email each time a space trigger critical is reached:
```bash
[fs]
critical=90
critical_action_repeat=echo {{device_name}} {{percent}} > /tmp/fs.alert && python /etc/glances/actions.d/fs-critical.py
```

Within /etc/glances/actions.d/fs-critical.py:
```python
import subprocess
from requests import get

fs_alert = open('/tmp/fs.alert', 'r').readline().strip().split(' ')
device = fs_alert[0]
percent = fs_alert[1]
system = subprocess.check_output(['uname', '-rn']).decode('utf-8').strip()
ip = get('https://api.ipify.org').text

body = 'Used user disk space for ' + device + ' is at ' + percent + '%.\nPlease cleanup the filesystem to clear the alert.\nServer: ' + str(system)+ '.\nIP address: ' + ip
ps = subprocess.Popen(('echo', '-e', body), stdout=subprocess.PIPE)
subprocess.call(['mail', '-s', 'CRITICAL: disk usage above 90%', '-r', 'postmaster@example.com', 'glances@example.com'], stdin=ps.stdout)
```


```bash
# ...

[cpu]
user_careful=50
user_warning=70
user_critical=90
user_log=False
user_critical_action=echo {{user}} {{value}} {{max}} > /tmp/cpu.alert

# ...
```



