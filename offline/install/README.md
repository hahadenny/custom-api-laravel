
# Porta On Prem

## Installing Porta On Prem
- [Installation and Updates](INSTALL.html)
- [Installation Troubleshooting](installation-troubleshooting.html)

## Post-Installation
- [Logs & Diagnostics](#logs--diagnostics)
  - [Logs](#logs)
    - [Installer Logs](#installer-logs)
    - [Porta Server & Service Logs](#porta-server--service-logs)
  - [Diagnostics](#diagnostics)
  - [Backups and Restores](#backups-and-restores)

## See Also
- [Initial Machine Setup](docs/machine-setup.html)
- [Group Replication Member Recovery](docs/replication-group-member-recovery.html)
- [Support Cheatsheet](docs/support-cheatsheet.html)

# Logs & Diagnostics
## Logs
### Installer Logs
Porta installer logs are located in the WSL directory: `/home/$USER/porta/logs/installer`

### Porta Server & Service Logs
Porta related logs are located within their respective containers. You can view some of these logs by running one of the helpers within the `porta-helpers/porta-log-viewers` folder, or by using the `docker logs <container-name>` command in Powershell.

## Diagnostics & Monitoring
### Full Diagnostic
Run a Porta diagnostic to compile various logs and configuration information into a single archive.

1. In porta-onprem-bundle's `porta-helpers` folder, run the full diagnostic helper by double-clicking on `porta-full-diag.bat`
2. The diagnostic will run and create a directory and `.tar.gz` file in the same folder as the helper script.

### Socketio Admin UI
View the Socketio Admin UI at `http://<PORTA-MACHINE-IP>:8000/socketio-adminui` to monitor the status of the Porta Socket Server. Please contact support for the login credentials.

### Metrics
Porta On Prem includes a Prometheus instance for monitoring metrics. You can access the Prometheus UI at `http://<PORTA-MACHINE-IP>:9090`.

# Backups and Restores
Please refer to the [Porta On Prem Backup & Restore Guide](docs/disaster-backup-and-restore.html) for details on how to backup and restore your Porta On Prem databases.
