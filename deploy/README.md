# Background services

The deployment contains two services on each supported platform:

- `Document Interrogation MCP` runs `Application/MCP_Server/mcp_client.py`.
- `Document Interrogation Queue` processes all queued jobs currently dispatched
  by this application through Laravel's default queue.

## Linux (systemd)

Create the Python virtual environment in `Application/MCP_Server/.venv` first.
Then install and start both units from the repository root:

```bash
sudo bash deploy/linux/install-services.sh /opt/Document-Interrogation-System www-data
```

The optional third and fourth arguments are the PHP executable and service
group:

```bash
sudo bash deploy/linux/install-services.sh /opt/Document-Interrogation-System app /usr/bin/php app
```

Useful commands:

```bash
sudo systemctl status document-interrogation-mcp document-interrogation-queue
sudo journalctl -u document-interrogation-mcp -f
sudo journalctl -u document-interrogation-queue -f
sudo systemctl restart document-interrogation-mcp document-interrogation-queue
```

## Windows

Windows cannot run an ordinary Python or PHP console process directly as a
Windows Service. Install NSSM and run PowerShell as Administrator:

```powershell
.\deploy\windows\install-services.ps1 -ProjectRoot "D:\apps\Document-Interrogation-System"
```

When PHP or NSSM is not in `PATH`, specify each executable:

```powershell
.\deploy\windows\install-services.ps1 `
    -ProjectRoot "D:\apps\Document-Interrogation-System" `
    -PhpPath "C:\php\php.exe" `
    -NssmPath "C:\tools\nssm\win64\nssm.exe"
```

The installer is idempotent: rerunning it updates and restarts both services.
To remove them:

```powershell
.\deploy\windows\uninstall-services.ps1 -NssmPath "C:\tools\nssm\win64\nssm.exe"
```

## Laravel scheduler

The queue worker processes queued jobs, but it does not run Laravel scheduled
commands. This project also schedules `documents:cleanup-deleted`. If that task
must run in production, configure the standard scheduler separately (`php
artisan schedule:work` or one cron invocation of `schedule:run` per minute).
