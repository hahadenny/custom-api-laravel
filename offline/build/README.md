# Build Docker Images

**Aside from vars which will be set during install, make sure the source code `.env` files have the correct values before building**  
You can get the correctly populated .env files from the [porta-env-files S3 bucket](https://us-east-1.console.aws.amazon.com/s3/buckets/porta-env-files?region=us-east-1&bucketType=general&tab=objects)
- offline/utils/.env-api (.env-api-onprem in bucket)
- offline/utils/.env-app (.env-app-onprem in bucket)
- offline/utils/.env-socket (.env-socket-onprem in bucket)

_**NOTE: make sure line endings of all script files are LF not CRLF**_

1. Locally edit `BUNDLE_BUILD_PATH` inside `porta-api/offline/utils/vars.sh` to set the destination dir of the build files.

2. Make sure Docker is running.

3. Grant the build script permissions & Run:
    ```bash
    # run from the /offline directory under project root 
    cd offline && chmod u+x build/build.sh && build/build.sh
    ```
    
    You will be prompted to submit the name of the branch for each repo that should be used to build. _NOTE: If an error is encountered, the build process will exit._ It can take up to 10 minutes for all of the images to finish building. 

4. When the images finish building, you'll be prompted with `Commit & push new version 'X.X.X' to each repo branch and docker hub? (y/n):` enter `y` to commit the new version as a tag to the branch and to docker hub, otherwise enter `n` to skip the commit/push.

When the builds have finished, **move on to the Setup step** to create the files that will be transferring to another machine.

# Troubleshooting

## WSL (36458) ERROR: UtilAcceptVsock:249: accept4 failed
If you encounter this error, you may need to restart the WSL service. 
- close all WSL terminals
- shut down docker
- shut down IDEs

In powershell:
```bash
wsl --shutdown
```

Then restart docker and open a new WSL terminal.

For other potential solutions (but NOT `[interop] enable = true`), also see: https://github.com/microsoft/WSL/issues/8677

## supervisorctl not usable  -- `Error: .ini file does not include supervisorctl section`
> https://askubuntu.com/questions/911940/error-ini-file-does-not-include-supervisorctl-section

_!!NOTE: If supervisord was started with `-c /etc/supervisord.conf`, as in the default porta container Dockerfile, then commands like `service supervisor stop` WILL NOT WORK!!! You will NEED to have `supervisorctl` working instead._

If there is no `/var/run/supervisor.sock` file, then add the following to the supervisor.conf file that you are using:
> https://serverfault.com/questions/808732/supervisor-sock-file-missing

```bash
[inet_http_server]
port=127.0.0.1:9001

[rpcinterface:supervisor]
supervisor.rpcinterface_factory = supervisor.rpcinterface:make_main_rpcinterface

[supervisorctl]
serverurl=http://127.0.0.1:9001
```

Then restart the supervisor service (or the whole container).

View `supervisorctl` with the following command:
```bash
supervisorctl status
```
