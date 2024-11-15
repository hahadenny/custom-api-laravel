# Instructions

Installation and Update instructions **must be run for each machine running Porta On-Prem**.

- [Setting up the Machine](#setting-up-the-machine)
    - [WSL](#wsl)
        - [Offline Installation (No Internet Access and No Microsoft Store)](#offline-installation-no-internet-access-and-no-microsoft-store)
        - [Online Installation (Machine has Internet Access)](#online-installation-machine-has-internet-access)
        - [Set default distro](#set-default-distro)
    - [Docker](#docker)
- [Troubleshooting](#troubleshooting)


# First Time Installation

## Setting up the Machine

> We'll be using Windows Subsystem for Linux and Windows cmd, Powershell, or Terminal to interact with docker.

### WSL

#### Offline Installation (No Internet Access and No Microsoft Store)
1. On a device that has internet access, download:
    - [WSL Kernel Update 5.10.102.2 for x64 arch](https://catalog.s.download.windowsupdate.com/d/msdownload/update/software/updt/2022/03/wsl_update_x64_8b248da7042adb19e7c5100712ecb5e509b3ab5f.cab)
    - [WSL 2 version 2.0.14 x64 arch](https://github.com/microsoft/WSL/releases/download/2.0.14/wsl.2.0.14.0.x64.msi)
    - [Ubuntu 22.04 WSL2 image](https://aka.ms/wslubuntu2204)
    - [Docker Desktop 4.28.0](https://desktop.docker.com/win/main/amd64/139021/Docker%20Desktop%20Installer.exe)
2. Copy the downloaded files to the machine being installed
3. Open the windows start menu, search for `Windows Features`, and select `Turn Windows features on or off`
4. Enable the following features if they are not checked already:
    - Virtual Machine Platform
    - Windows Subsystem for Linux
5. In the files you downloaded and transferred to the machine, double-click `wsl_update_x64_XXXXXX.cab` to extract the WSL2 kernel .msi file
6. Run `wsl_update_x64.msi` to install the WSL2 kernel update
7. Open powershell as administrator
8. In powershell, run `dism.exe /online /enable-feature /featurename:VirtualMachinePlatform /all /norestart` to enable the new version
9. Double-click `wsl.2.0.14.0.x64.msi` to update WSL to version 2.0.14
10. Reboot the machine
11. Open powershell as administrator
12. Run `wsl --set-default-version 2`
13. Check your WSL version with `wsl --version` (or `wsl --status`)
     - the WSL version should be 2.0.14
     - the Kernel version should be 5.10.102.2
14. Rename the Ubuntu AppxBundle extension from `.AppxBundle` to `.zip`
15. Extract this zip archive
16. Inside the extracted folder, use the file with the name ending in `_x64.appx` in the next step (likely `Ubuntu_2204.1.7.0_x64.appx`)
17. Open Powershell as administrator, navigate to the folder containing the .appx and run the following command in that directory, where `app-name` is the name of the Linux distribution `.appx` file: `Add-AppxPackage .\Ubuntu_2204.1.7.0_x64.appx`
18. Once the Appx package has finished, you can start running the new distribution by [running the following steps](https://learn.microsoft.com/en-us/windows/wsl/install-on-server#extract-and-install-a-linux-distribution) (The command `wsl -l` will not show that the distribution is installed until this step is complete):
    1. Add your Linux distribution path to the Windows environment PATH (C:\Users\Administrator\Ubuntu in this example), using PowerShell:
       ```powershell 
       $userenv = [System.Environment]::GetEnvironmentVariable("Path", "User")
       [System.Environment]::SetEnvironmentVariable("PATH", $userenv + ";C:\Users\Administrator\Ubuntu", "User")
       ```
    2. In powershell, run `ubuntu2204.exe`. This will begin installing the Ubuntu 22.04 distro and prompt for user setup.
    3. When prompted for a username and password, enter a new username and password (this user will have `sudo` aka admin permissions)
       - The username must contain only lowercase letters, underscores, and dashes
       - **Do not proceed with the `root` user**, this is a security risk and will cause issues with the installation 
    > You may not need to add to PATH, the exe should be located at `%LOCALAPPDATA%\Microsoft\WindowsApps` in the folder that looks like `CanonicalGroupLimited.Ubuntu22.04LTS...` and you could try just clicking `ubuntu2204.exe` or calling the full path from powershell in order to start the Ubuntu install
19. After distro install completes, back in powershell, you should see `Ubuntu-22.04` listed if you run `wsl -l -v`
    - If you aren't seeing `Ubuntu-22.04` listed, try checking the distro in use by starting WSL and checking the OS version by running `wsl` in Powershell, and then running `cat /etc/os-release`. The output should look something like:
      ``` 
      PRETTY_NAME="Ubuntu 22.04.4 LTS"
      NAME="Ubuntu"
      VERSION_ID="22.04"
      VERSION="22.04.4 LTS (Jammy Jellyfish)"
      VERSION_CODENAME=jammy
      ID=ubuntu
      ID_LIKE=debian
      HOME_URL="https://www.ubuntu.com/"
      SUPPORT_URL="https://help.ubuntu.com/"
      BUG_REPORT_URL="https://bugs.launchpad.net/ubuntu/"
      PRIVACY_POLICY_URL="https://www.ubuntu.com/legal/terms-and-policies/privacy-policy"
      UBUNTU_CODENAME=jammy
      ```
20. Reboot the machine
21. Reopen powershell as administrator
22. Double-check your install: `wsl --status`, you should see:
    - `Default Distribution: Ubuntu-22.04`
    - `Default Version: 2`
    - **_If you receive "WSL2 is not supported with your current machine configuration" then you need to enable virtualization in the BIOS_**. This process will vary depending on your machine.
23. Make sure Ubuntu is using WSL2 by running `wsl -l -v --all` this should look something like:
    ```
    NAME                   STATE           VERSION
    * Ubuntu-22.04           Running         2
    ```
24. Ensure Ubuntu is used as the default distro by running this command in Powershell:
    - `wslconfig /s Ubuntu-22.04`
25. You can confirm by running `wsl --status` again, or `wslconfig /l`
26. Now you can move on to the [Docker installation](#docker) and Porta On Prem steps

For more details, see the [Official Microsoft WSL manual install instructions](https://learn.microsoft.com/en-us/windows/wsl/install-manual).

Other related links:
- WSL Kernel releases: https://github.com/microsoft/WSL2-Linux-Kernel/releases
- KBXXXX Win10 update packages: https://support.microsoft.com/en-us/topic/kb5003791-update-to-windows-10-version-21h2-by-using-an-enablement-package-8bc077be-18d7-4aac-81ce-6f6dad2cd384
- Running `wsl --update` manually (update WSL kernel): https://wslstorestorage.blob.core.windows.net/wslblob/wsl_update_x64.msi
- Installing from .appx without microsoft store: https://learn.microsoft.com/en-us/windows/wsl/install-on-server#extract-and-install-a-linux-distribution
- (?) Creating custom WSL images: https://learn.microsoft.com/en-us/windows/wsl/enterprise#creating-a-custom-wsl-image
- (?) Importing linux distro to WSL: https://learn.microsoft.com/en-us/windows/wsl/use-custom-distro



#### Online Installation (Machine has Internet Access)
1. Download the following files to the machine being installed:
    - [WSL Kernel Update 5.10.102.2 for x64 arch](https://catalog.s.download.windowsupdate.com/d/msdownload/update/software/updt/2022/03/wsl_update_x64_8b248da7042adb19e7c5100712ecb5e509b3ab5f.cab)
    - [WSL 2 version 2.0.14 x64 arch](https://github.com/microsoft/WSL/releases/download/2.0.14/wsl.2.0.14.0.x64.msi)
    - [Ubuntu 22.04 WSL2 image](https://aka.ms/wslubuntu2204)
    - [Docker Desktop 4.28.0](https://desktop.docker.com/win/main/amd64/139021/Docker%20Desktop%20Installer.exe)
2. Open the windows start menu, search for `Windows Features`, and select `Turn Windows features on or off`
3. Enable the following features if they are not checked already:
    - Virtual Machine Platform
    - Windows Subsystem for Linux
4. You may need to reboot the machine after enabling these features
4. In the files you downloaded and transferred to the machine, double-click `wsl_update_x64_XXXXXX.cab` to extract the WSL2 kernel .msi file
5. Run `wsl_update_x64.msi` to install the WSL2 kernel update
   - If you encounter an error stating that this update is only available to machines with Windows Subsystem for Linux enabled, then:
     - Open powershell as administrator
     - Ensure that WSL is enabled by running `wsl --install`
     - Reboot the machine
6. Open powershell as administrator
7. In powershell, run `dism.exe /online /enable-feature /featurename:VirtualMachinePlatform /all /norestart` to enable the new version
8. Double-click `wsl.2.0.14.0.x64.msi` to update WSL to version 2.0.14
9. Reboot the machine
10. Open powershell as administrator
11. Run `wsl --set-default-version 2`
12. Check your WSL version with `wsl --version` (or `wsl --status`)
    - the WSL version should be 2.0.14
    - the Kernel version should be 5.10.102.2
13. Run `wsl --install -d Ubuntu-22.04`
     - confirm any permission prompts
     - An alternate command may be needed: `wsl --install Ubuntu-22.04 --web-download`
     - If Ubuntu 22.04 is not available (run `wsl --list --online` to see available linux distros), and your machine has internet access, use `Ubuntu` or `Ubuntu-20.04` as the distro and proceed with the steps.
     - **_IMPORTANT: If the necessary Ubuntu version is not available, the PX Machine Image should be updated to correctly support the software it is intended to run._**
14. Wait for the installation to finish
15. Reboot your PC (if it wasn't already installed)
16. After startup, the Ubuntu CLI should open automatically and prompt you for a username and password
     - Enter a username and password (this user will have sudo permissions)
     - After the "Installation successful!" message, in the terminal that opens, run:
         - `sudo apt update`
     - Then run the following and approve any confirmations:
         - `sudo apt upgrade`
17. If you had to install with `Ubuntu` or `Ubuntu-20.04` instead of `Ubuntu-22.04`, you will need to upgrade to Ubuntu 22.04:
     - `sudo apt full-upgrade`
     - Close the terminal window and open a new WSL terminal
     - Run the following command to upgrade to Ubuntu 22.04:
         - `y | sudo do-release-upgrade`
             - The command will take a few minutes to run and you will need to confirm the upgrade when prompted.
             - This prompt will happen one or two times.
     - After the upgrade is complete, close the terminal
     - Open a new WSL terminal and check its release information to ensure Ubuntu 22.XX is installed:
         - `cat /etc/os-release`
         - The `VERSION_ID` should be `22.04`
18. If you rebooted, reopen Powershell **as administrator**
19. Run `wsl --update`
    - See the troubleshooting section "Error: 0x8024500c after wsl --update" below if you have issues with this command
20. Restart WSL (if it was updated): `wsl --shutdown`
21. Double-check your install: `wsl --status`, you should see:
    - `Default Distribution: Ubuntu-22.04`
    - `Default Version: 2`
    - If you receive "WSL2 is not supported with your current machine configuration" then you need to enable virtualization in the BIOS. This process will vary depending on your machine.
22. Make sure Ubuntu is using WSL2 by running `wsl -l -v --all` this should look something like:
    ```
    NAME                   STATE           VERSION
    * Ubuntu-22.04           Running         2
    ```

For more details, see the [Official Microsoft WSL install instructions](https://learn.microsoft.com/en-us/windows/wsl/install)

#### Set default distro
Once Ubuntu 22.04 is installed, make sure it's your default distro by running this command in Windows Powershell:
```
wslconfig /s Ubuntu-22.04
```

You can confirm by running
```
wslconfig /l
```

### Docker

Download and install Docker Desktop for Windows, version 4.28.0:
    - https://desktop.docker.com/win/main/amd64/139021/Docker%20Desktop%20Installer.exe

Follow the installer instructions for WSL2 backend and, when prompted in docker, ensure the **Use WSL 2 instead of Hyper-V** option on the Configuration page is selected.

1. Make sure Docker is running

2. Set Docker Desktop to start on login
    - In the top right menu bar, click the gear icon to open settings
    - Select the left sidebar's General tab to pull up the General settings
    - Check the box "Start Docker Desktop when you log in"
    - In the bottom right, click "Apply & Restart"
    - Using the inner "X" icon in the top right, close the Settings menu

3. **Disable Updates** in Docker Desktop Settings
    - In the top right menu bar, click the gear icon to open settings
    - Select the left sidebar's Software updates tab to pull up the updates settings
    - **Uncheck** the box "Automatically check for updates"
    - **Uncheck** the box "Automatically download updates"
    - In the bottom right, click "Apply & Restart"
    - Using the inner "X" icon in the top right, close the Settings menu

4. Set up the Windows user to auto-login
    - A sales engineer, support engineer or IT specialist may need to help with this step

5. Open the `hosts` file **as administrator** in Windows (Located in `C:\Windows\System32\drivers\etc`)
    - This is often most easily done by opening the start menu and typing `notepad` to search for the Notepad application
    - Right-click the Notepad application and select "Run as administrator"

6. Copy & paste the following entries at the bottom of the `hosts` file and replace each `<XXXXXXX MACHINE IP>` with the appropriate machine IPs. Save the `hosts` file as type `All files (*.*)` (it should have NO file extension).
    ```plain
    <CURRENT MACHINE IP> porta-socket.server
    <MAIN MACHINE IP> porta-db
    <BACKUP MACHINE IP> porta-db-2
    <ARBITER MACHINE IP> porta-db-3
    ```
    - _**NOTE**: The IP address required here **cannot be** 127.0.0.1. It is the IPv4 address for the machine's current network connection. The IPv4 address can be observed by running `ipconfig` in Powershell or cmd.exe and looking under the correct network adapter's (often d3net) value for IPv4._

7. Add inbound rules to open mysql ports **on EACH machine**:
    1. In Windows Security --> Firewall & network protection --> Advanced Settings --> Inbound Rules --> Add Rule
    2. Choose "Ports" and add paste these as local ports: `3306,3307,3308,33061,33062,33063`
    3. Finish up the wizard steps


---

# Troubleshooting

## Database Replication
For database troubleshooting, see [group-replication-troubleshooting.html](group-replication-troubleshooting.html)

## `wsl --install -d Ubuntu-22.04` gives `Error Code 0x80072ee7`
WSL/Ubuntu set up will need to be done using the offline steps in the ["Offline Installation (No Internet Access and No Microsoft Store)" section above](#offline-installation-no-internet-access-and-no-microsoft-store).


## WslRegisterDistribution failed with error: 0x80370102
This error may occur when rebooting after `wsl --install`.  
Follow the WSL instructions to enable virtualization in the BIOS, then continue with the install steps from earlier.


## `ERROR: CreateProcessParseCommon:711: Failed to translate` when running `wsl` commands
Make sure that Ubuntu was set as a default distro, this error often indicates that Docker's distro is being used instead of Ubuntu. Though soem commands like `wsl -l -v --all` still worked.
This error displayed as:
```
PS C:\Users\administrateur> wsl
<3>WSL (27) ERROR: CreateProcessParseCommon:711: Failed to translate C:\Users\administrateur
<3>WSL (27) ERROR: CreateProcessParseCommon:757 : getpwuid(0) failed 2
<3>WSL (27) ERROR: UtilTranslatePathList:2866: Failed to translate C:\Program Files\WSL
<3>WSL (27) ERROR: UtilTranslatePathList:2866: Failed to translate C:\WINDOWS\system32
<3>WSL (27) ERROR: UtilTranslatePathList:2866: Failed to translate C:\WINDOWS
<3>WSL (27) ERROR: UtilTranslatePathList:2866: Failed to translate C:\WINDOWS\System32\Wbem
<3>WSL (27) ERROR: UtilTranslatePathList:2866: Failed to translate C:\WINDOWS\System32\WindowsPowerShell\v1.0\
<3>WSL (27) ERROR: UtilTranslatePathList:2866: Failed to translate C:\WINDOWS\System32\OpenSSH\
<3>WSL (27) ERROR: UtilTranslatePathList:2866: Failed to translate C:\Program Files\dotnet\
<3>WSL (27) ERROR: UtilTranslatePathList:2866: Failed to translate C:\Program Files\Docker\Docker\resources\bin
<3>WSL (27) ERROR: UtilTranslatePathList:2866: Failed to translate C:\Users\administrateur\AppData\Local\Microsoft\WindowsApps
<3>WSL (27) ERROR: UtilTranslatePathList:2866: Failed to translate C:\Users\administrateur\Ubuntu
Processing fstab with mount -a failed.
Failed to mount C:\, see dmesg for more details.
Failed to mount D:\, see dmesg for more details.
Failed to mount C:\, see dmesg for more details.
Failed to mount D:\, see dmesg for more details.

<3>WSL (27) ERROR: CreateProcessEntryCommon:334: getpwuid(0) failed 2
<3>WSL (27) ERROR: CreateProcessEntryCommon:505: execvpe /bin/sh failed 2
<3>WSL (27) ERROR: CreateProcessEntryCommon:508: Create process not expected to return
```

## The Ubuntu user password was not documented/forgotten
You can reset an Ubuntu user's password by running the following commands in Windows Powershell:
```powershell
wsl -u root
```
This will log you into the WSL terminal as the root user. Then run the following command to change the password for the other user, replacing `<username>` (removing `<` and `>`) with the username you need to change:
```bash
passwd <username>
```

## Ubuntu only has a `root` user
If you only have a `root` user in Ubuntu, you must create a new user and set it as default by running the following commands.  

In Powershell, run the `wsl` command to open the Ubuntu terminal:
```powershell
wsl
```

In the Ubuntu terminal, run the following commands to create a new user   
_Replace `<username>`, including the `<` brackets, with the desired username. Usernames must contain only lowercases letters, underscores, and dashes_
```bash
adduser <username>
```
You will be prompted to enter: 
- a password for the new user, **make sure to document this password**!
- several other name & number details, which should be skipped by pressing `Enter`
- confirm by typing `Y` and pressing `Enter`

Then, add the user to the `sudo` group to give it admin permissions
```bash
usermod -aG sudo <username>
```

And add the user to the `docker` group to give it access to docker
```bash
usermod -aG docker <username>
```

Delete root's copy of porta (in case it exists)
```bash
rm -rf /home/root/porta
```

Finally, return to Powershell by typing `exit` and hitting `Enter` in the Ubuntu terminal, and then run the following command to set the new user as the default user for WSL:
```powershell 
ubuntu config --default-user <username>
```

## Permission denied while trying to connect to the Docker daemon socket
Your ubuntu user may not have permission to use docker.

1. Make sure the docker group exists
```
sudo groupadd docker
```

2. Add your user to the docker group (`${USER}` will automatically fill in your username when run).
```
sudo usermod -aG docker ${USER}
```

3. Log out and back in so that your groups are refreshed:
```
su -s ${USER}
```
If the above command doesn't appear successful, you can just close the terminal window and re-open it to accomplish the same thing

4. Check your groups; you should see docker listed
```
groups
```

5. Verify that you can now use docker commands
   (If you have no internet access, this command may fail and that is okay, you just don't want to see the same "permission denied" error)
```
docker run hello-world
```

6. Try running the install script again


## WARNING: Error loading config file: /home/user/.docker/config.json - stat /home/user/.docker/config.json: permission denied
Change `.docker`'s ownership and permissions using the following commands (`$USER` will automatically fill in your username when run):
```
sudo chown "$USER":"$USER" /home/"$USER"/.docker -R
sudo chmod g+rwx "$HOME/.docker" -R
```

## Docker Desktop - Unexpected WSL error
An unexpected error was encountered while executing a WSL command…

In Powershell (not cmd or WSL), check `wsl --status`, if you receive "WSL2 is not supported with your current machine configuration" then you need to enable virtualization in the BIOS. This process will vary depending on your machine.


## Docker Installation Errors - "wsl update failed" or similar
If you encounter an error regarding WSL updates when installing Docker Desktop, make sure WSL is up to date by following the instructions in the troubleshooting section "[Error: 0x8024500c after wsl --update](#error-0x8024500c-after-wsl---update)", then uninstall Docker Desktop and re-install it.

If you continue to encounter errors regarding WSL updates, uninstall Docker Desktop and re-install it, but this time under Configuration, DON’T check Use WSL2 instead of Hyper-V.

Once install has completed, Docker will prompt you to reboot. After reboot, start Docker Desktop again.

Navigate to Settings

Check "Use the WSL2 based engine", then select "Apply & restart".  


## Error: 0x8024500c after wsl --update
Some installs with Windows 10, or without using the Microsoft store, will display this error. Check the current WSL Kernel version by running `wsl --version` (or `wsl --status`) in Powershell and checking the entry for `Kernel version`, which should begin with `5` (we do NOT want `WSL Version`, that is different).  

If the Kernel version is not greater than or equal to `5.10.102.2`, then we will need to manually update the kernel:

- Visit [Microsoft's update catalog](https://www.catalog.update.microsoft.com/Search.aspx?q=windows%20subsystem%20for%20linux%20update) and sort by last updated. _As of writing, the latest version is `5.10.102.2`_. 
- Download the `.cab` file for the latest version
- Double-click the `.cab` file to extract the `.msi` file within in
- Run `wsl_update_x64.msi` to install the WSL2 kernel update
- Open powershell as administrator
- In powershell, run `dism.exe /online /enable-feature /featurename:VirtualMachinePlatform /all /norestart` to enable the new version
- Run `wsl --set-default-version 2`
- Check your WSL version with `wsl --version` (or `wsl --status`)
    - the WSL version should be 2.X.X (start with 2)
    - the Kernel version should be 5.10.102.2

    
Check the WSL version by running `wsl --version` (or `wsl --status`) in Powershell. If the `wsl --version` and `wsl --status` commands do not work, try running:
```Powershell
(Get-AppxPackage | ? Name -eq "MicrosoftCorporationII.WindowsSubsystemforLinux").Version
```

If the version listed is not greater than or equal to `2.0.14`, then you will need to manually update WSL by taking the following steps (as listed in Updating WSL 2 without Microsoft Store) :

Download the `2.0.14` WSL app package and msi release from the [WSL Releases page in the Github repo](https://github.com/microsoft/WSL/releases/tag/2.0.14). This will be the assets with file extension `.msixbundle` and `.msi`. 

Run `wsl --shutdown` and reboot to make sure that WSL is not in use at all.  

Start an Administrator PowerShell and run the following (adjust the package name for the latest version you downloaded):
```Powershell
Add-AppxPackage <path.to>/Microsoft.WSL_1.0.0.0_x64_ARM64.msixbundle
```

If you run into problems regarding windows store, try installing using the `.msi` file instead. 

After installing, check the new WSL version with `wsl --version` or `(Get-AppxPackage | ? Name -eq "MicrosoftCorporationII.WindowsSubsystemforLinux").Version`

Reboot
