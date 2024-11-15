
# Instructions

Installation and Update instructions **must be run for each machine running Porta On-Prem**.  

- [First Time Installation](#installing-porta-on-prem)
- [Update Existing Installation](#update-existing-installation)
- [Porta Integrations Setup](#porta-integrations-setup)
- [Installation/Update Troubleshooting](troubleshooting.html)

# See Also
- [Initial Machine Setup](docs/machine-setup.html)
- [Disaster Backup & Restore](docs/disaster-backup-and-restore.html)
- [Support Cheatsheet](docs/support-cheatsheet.html)
- [Group Replication Troubleshooting](docs/group-replication-troubleshooting.html)
- [Group Replication Member Recovery](docs/replication-group-member-recovery.html)

---

# Requirements Before Installation
Please work with IT to ensure the machines are set up on the same network before beginning the installation process.

Please ensure that the machine has been prepared beforehand: [Initial Machine Setup](docs/machine-setup.html)

# Installing Porta On Prem
This process covers installing on a new machine, or running a fresh install for an existing installation so that it is wiped.

1. Make sure Docker is running

2. Transfer the porta-onprem-bundle.zip archive to the Desktop of machine being installed.
    - If you have internet access, this can be found at https://staging.porta.solutions under the Help menu > Plugins

3. Right-click and extract the files to the Desktop.

4. Open the unzipped porta-onprem-bundle folder and navigate to the `porta-helpers` folder.
    > This folder contains the `install` and `update` scripts that will be used to install and update Porta On Prem, as well as the `database` folder which contains the helper scripts for running database operations.

5. Right-click on `install.bat` and choose "Run as Administrator" to begin the installation process. 

6. If you are setting up multiple machines that will be running Porta On Prem, when you are prompted about database replication, type `y` and press enter, otherwise type `n` and press enter

7. When prompted, enter the Porta machine **type**, this would be `main`, `backup`, or `arbiter`, depending on which machine you are currently working with

8. When prompted, enter or confirm the Porta machine IP  
   _**NOTE**: The IP address required here **cannot be** 127.0.0.1. It is the IPv4 address for the machine's current network connection. The IPv4 address can be observed by running `ipconfig` in Powershell or cmd.exe and looking under the correct network adapter's (often d3net) value for IPv4._

9. If prompted, enter the IP for the other Porta machines

10. Wait for the installation to finish. It can take up to 10 minutes to complete. If an error occurs, the process will be aborted.  
    - You may be prompted with `gzip: porta-images.tar already exists; do you wish to overwrite (y or n)?` -- type `y` and press enter to continue  
    > NOTE: Error `Error occurred in command: 'xargs docker rmi'` after `Error response from daemon: conflict: unable to delete 143f1a610ec2 (must be forced) - image is being used by stopped container` will not abort, this is normal.

    **NOTE:** During installation of the Porta Front End, you may see what appear to be prompts for user input in the console terminal. **These are not actually prompts**, they should be ignored and the installation will continue after a few seconds.
    For example:
    ```bash
    ===
    ==================================================================================================
    =====> Building Porta Front End, this may take several minutes...
    ==================================================================================================
    ===
    
    
    > porta@0.1.0 build
    > GENERATE_SOURCEMAP=false react-scripts build
    
    Creating an optimized production build...
    Browserslist: caniuse-lite is outdated. Please run:
      npx update-browserslist-db@latest
      Why you should do it regularly: https://github.com/browserslist/update-db#readme
    ```

11. When the install is nearing completion, you will be prompted with `Should this database be bootstrapped?`. If this is the main machine, type `y` and press enter. If this is the backup or arbiter machine, type `n` and press enter.

12. When the installation is complete, you will see a message that the installation was successful, and shortcuts to Porta On Prem and the Porta On Prem backup will be created on the desktop.
 
13. You can double-check the version of Porta that was just installed by navigating to `porta-onprem-bundle/porta-helpers/porta-checks` and double-clicking on `check-version.bat`.

14. **If this was the main machine, repeat steps 1-16 on the back-up machine**
    - Be sure to wait for the main machine to finish installing before starting the backup machine; the main machine database must be running before the backup machine's database can be set up

15. **If this was the backup machine, repeat steps 1-16 on the arbiter machine** 
    - Be sure to wait for the main and backup machines to finish installing before starting the arbiter machine; the other databases must be running before the arbiter machine's database can be set up
    
16. See [Porta Integrations Setup](#porta-integrations-setup) to set up the Porta Bridge and Unreal plugins (if applicable)


---

# Update Existing Installation

1. On the desktop, if there is an existing `porta-onprem-bundle.zip`, rename it to `porta-onprem-bundle-old.zip`

2. On the desktop, open the existing `porta-onprem-bundle` folder, navigate to the `porta-helpers` folder, and move the `conf` folder to the desktop.
   > Thhis folder contains the configuration files for the Porta On Prem installation and we can avoid inputting machine info if we use it with the new installation

3. Follow steps 1 through 4 in the [#Installing-Porta-On-Prem](#installing-porta-on-prem) section above (they are the same for updates and new installs).

4. Move the `conf` folder from the desktop to the `porta-helpers` folder.

5. Navigate to the `database`folder, then double-click on `create-backup.bat` to create a backup of the database before updating.
    > Updating Porta On Prem will not overwrite the database, but it is always a good idea to have a backup before making changes.

6. In the `porta-helpers` folder, right-click on `update.bat` and choose "Run as Administrator" to begin the update process.

7. When prompted, enter the Porta machine **type**, this would be `main`, `backup`, or `arbiter`, depending on which machine you are currently working with

8. When prompted, enter or confirm the Porta machine IP
   _**NOTE**: The IP address required here **cannot be** 127.0.0.1. It is the IPv4 address for the machine's current network connection. The IPv4 address can be observed by running `ipconfig` in Powershell or cmd.exe and looking under the correct network adapter's (often d3net) value for IPv4._

9. If you are setting up multiple machines that will be running Porta On Prem, when you are prompted about database replication, type `y` and press enter, otherwise type `n` and press enter.

10. Wait for the update to continue. It can take up to 10 minutes for the update to complete. If an error occurs, the process will be aborted.
    - You may be prompted with `gzip: porta-images.tar already exists; do you wish to overwrite (y or n)?` -- type `y` and press enter to continue
    > **NOTE**: Error `Error occurred in command: 'xargs docker rmi'` after `Error response from daemon: conflict: unable to delete 143f1a610ec2 (must be forced) - image is being used by stopped container` will not abort, this is normal.
    
    **NOTE:** During installation of the Porta Front End, you may see what appear to be prompts for user input in the console terminal. These are not actually prompts, they should be ignored and the installation will continue after a few seconds. 
    For example:
    ```bash
    ===
    ==================================================================================================
    =====> Building Porta Front End, this may take several minutes...
    ==================================================================================================
    ===
    
    
    > porta@0.1.0 build
    > GENERATE_SOURCEMAP=false react-scripts build
    
    Creating an optimized production build...
    Browserslist: caniuse-lite is outdated. Please run:
      npx update-browserslist-db@latest
      Why you should do it regularly: https://github.com/browserslist/update-db#readme
    ```

11. When the update is nearing completion, you will be prompted with `Should this database be bootstrapped?`. The answer will depend on the current state of the database replication group. **On the other two machines**, we can check this by navigating to `porta-onprem-bundle/porta-helpers/porta-database/checks` and double-clicking on `view-group-repl-status.bat`. 
    - If the resulting output shows an entry with `MEMBER_STATE` of `ONLINE` and `MEMBER_ROLE` of `PRIMARY`, then the database is already bootstrapped and the answer should be `n`.
    - If the resulting output shows only one entry with `MEMBER_STATE` of `OFFLINE`, then this machine is not currently part of the replication group and status should be checked from another machine.
    - If each machine has been checked and given the `MEMBER_STATE` of `OFFLINE`, then it is safe to bootstrap this machine and the answer should be `y`.
    - This output should look something like:
    ```bash
    +---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
    | CHANNEL_NAME              | MEMBER_ID                            | MEMBER_HOST    | MEMBER_PORT | MEMBER_STATE | MEMBER_ROLE | MEMBER_VERSION | MEMBER_COMMUNICATION_STACK |
    +---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
    | group_replication_applier | 1a351ce3-ba19-11ee-9e59-0242ac120002 | 192.168.50.163 |        3306 | ONLINE       | PRIMARY   | 8.0.32         | XCom                       |
    | group_replication_applier | d7c0535c-ba19-11ee-9f28-0242ac140002 | 192.168.50.20  |        3307 | ONLINE       | SECONDARY   | 8.0.32         | XCom                       |
    | group_replication_applier | e4a0ac63-ba19-11ee-9f54-0242ac150002 | 192.168.50.42  |        3308 | ONLINE       | SECONDARY     | 8.0.32         | XCom                       |
    +---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
    ```  
    > **_!! NOTE !!   
Bootstrapping a database in an existing, healthy group can lead to data loss and corruption. If you are still unsure how to proceed, please contact support._**

12. If the database has not been bootstrapped, you will be prompted with `Should the database be migrated?`. If this is the final machine being updated, and no machines have been bootstrapped during their update process, type `y` and press enter. Otherwise, press enter to confirm `n`.  
13. If the database has not been bootstrapped, you will be prompted with `Should the database be seeded?`. **_If you have not been instructed to seed for this update, type or confirm `n`_**. Otherwise, if this is the final machine being updated, and no machines have been bootstrapped during their update process, type `y` and press enter. Otherwise, type `n` and press enter.
14. When the installation is complete, you will see a message that the installation was successful, and shortcuts to Porta On Prem and the Porta On Prem backup will be created on the desktop.

13. You can double-check the version of Porta that was just installed by navigating to `porta-onprem-bundle/porta-helpers/porta-checks` and double-clicking on `check-version.bat`.

14. **If this was the main machine, repeat steps 1-14 on the backup machine**
    - Be sure to wait for the main machine to finish installing before starting the backup machine; the main machine database must be running before the backup machine's database can be set up

15. **If this was the backup machine, repeat steps 1-14 on the arbiter machine**
    - Be sure to wait for the main and backup machines to finish installing before starting the arbiter machine; the other databases must be running before the arbiter machine's database can be set up

16. If applicable, update your Porta Bridge and Unreal plugins configurations



---

# Porta Integrations Setup

### Porta Bridge
- Run the Bridge as administrator
- Fill out the D3 Connection Configuration
- Before hitting the Start button, go to the menu Window > Porta
  - Make sure the Socket Server URL is `http://porta-socket.server:6001` 
  - Make sure the API URL is `http://<PORTA-MACHINE-IP>:6001` 
    - Replace `<PORTA-MACHINE-IP>` with the IP address of the current machine running Porta
  - Make sure the Http Listener IP Address is `<BRIDGE-MACHINE-IP>`
    - Replace `<BRIDGE-MACHINE-IP>` with the IP address of the machine running the Bridge. 
    - This is likely the same as the Porta machine IP if the Bridge is running on the same machine as Porta 
- Go to Window > D3 and hit the Start button to connect Porta to D3 

### Unreal Plugin
- Connection URL: `http://porta-socket.server:6001`  
- API URL: `http://<PORTA-MACHINE-IP>:8000`
  - Replace `<PORTA-MACHINE-IP>` with the IP address of the current machine running Porta


### Access Porta in Chrome
Use the "Main Company" -- **ensure the socket server is set to `http://<PORTA-MACHINE-IP>:6001`, NOT `http://porta-socket.server`**

Login Credentials:
- email: `superadmin@disguise.one`
- password: `password`

**Be sure to access Porta using its machine IP, NOT `localhost`**

Porta: `http://<PORTA-MACHINE-IP>:8080/`  
Porta API: `http://<PORTA-MACHINE-IP>:8000/`
