# Snoozer Deployment Guide - Windows Server 2025 with IIS

This guide covers deploying Snoozer on Windows Server 2025 with IIS, MySQL, and PHP.

## Prerequisites

- Windows Server 2025
- Administrator access
- Domain name pointing to your server (e.g., `app.snoozer.cloud`)
- Internet connection for downloading components

## 1. Install IIS with Required Features

Open PowerShell as Administrator and run:

```powershell
# Install IIS with required features
Install-WindowsFeature -Name Web-Server -IncludeManagementTools
Install-WindowsFeature -Name Web-Mgmt-Console
Install-WindowsFeature -Name Web-Default-Doc
Install-WindowsFeature -Name Web-Dir-Browsing
Install-WindowsFeature -Name Web-Http-Errors
Install-WindowsFeature -Name Web-Static-Content
Install-WindowsFeature -Name Web-Http-Logging
Install-WindowsFeature -Name Web-Request-Monitor
Install-WindowsFeature -Name Web-Filtering
Install-WindowsFeature -Name Web-Stat-Compression
Install-WindowsFeature -Name Web-Dyn-Compression

# Verify installation
Get-WindowsFeature -Name Web-Server
```

## 2. Install PHP 8.x

### Download and Install PHP

1. Download PHP 8.3 (Non-Thread Safe) from [windows.php.net](https://windows.php.net/download/)
2. Extract to `C:\PHP`

```powershell
# Create PHP directory
New-Item -Path "C:\PHP" -ItemType Directory -Force

# Download PHP (example - adjust URL for latest version)
# Download manually from https://windows.php.net/download/ and extract to C:\PHP
```

### Configure PHP

1. Copy `C:\PHP\php.ini-production` to `C:\PHP\php.ini`
2. Edit `C:\PHP\php.ini`:

```ini
; Enable required extensions
extension_dir = "C:\PHP\ext"
extension=mysqli
extension=mbstring
extension=openssl
extension=curl
extension=imap
extension=fileinfo

; Configuration
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 120
memory_limit = 256M
date.timezone = UTC
display_errors = Off
log_errors = On
error_log = "C:\inetpub\logs\php_errors.log"

; IMAP settings
imap.enable_insecure_rsh = Off
```

### Add PHP to System PATH

```powershell
# Add PHP to PATH
$env:Path += ";C:\PHP"
[Environment]::SetEnvironmentVariable("Path", $env:Path, [System.EnvironmentVariableTarget]::Machine)

# Verify PHP installation
php -v
php -m | Select-String -Pattern "mysqli|imap|mbstring|openssl"
```

## 3. Install URL Rewrite Module for IIS

Download and install the URL Rewrite module:
- Download from: https://www.iis.net/downloads/microsoft/url-rewrite
- Or install via Web Platform Installer

```powershell
# Verify URL Rewrite is installed
Get-WebConfigurationProperty -pspath 'MACHINE/WEBROOT/APPHOST' -filter "system.webServer/rewrite/rules" -name "."
```

## 4. Configure IIS for PHP

### Install PHP Handler

```powershell
# Register PHP with IIS using FastCGI
New-WebHandler -Name "PHP-FastCGI" -Path "*.php" -Verb "*" -Modules "FastCgiModule" -ScriptProcessor "C:\PHP\php-cgi.exe" -ResourceType File

# Configure FastCGI settings
Add-WebConfigurationProperty -pspath 'MACHINE/WEBROOT/APPHOST' -filter "system.webServer/fastCgi" -name "." -value @{fullPath='C:\PHP\php-cgi.exe'}

# Set default document
Add-WebConfigurationProperty -pspath 'MACHINE/WEBROOT/APPHOST' -filter "system.webServer/defaultDocument/files" -name "." -value @{value='index.php'}
```

## 5. Install MySQL Server

### Download and Install MySQL

1. Download MySQL 8.0 Community Server from [dev.mysql.com](https://dev.mysql.com/downloads/mysql/)
2. Run the installer and select "Server only" or "Developer Default"
3. Configure MySQL:
   - Choose "Development Computer" or "Server Computer"
   - Set root password (save this securely)
   - Configure as Windows Service (start automatically)

### Create Database and User

Open MySQL Command Line Client or MySQL Workbench:

```sql
CREATE DATABASE `snoozer-app` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'snoozeradmin'@'localhost' IDENTIFIED BY 'YOUR_SECURE_PASSWORD';
GRANT ALL PRIVILEGES ON `snoozer-app`.* TO 'snoozeradmin'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Import Database Schema

```powershell
# Navigate to your application directory
cd C:\inetpub\wwwroot\snoozer

# Import schema
mysql -u snoozeradmin -p snoozer-app < Database_Export.sql
```

## 6. Deploy Application Files

### Create Application Directory

```powershell
# Create application directory
New-Item -Path "C:\inetpub\wwwroot\snoozer" -ItemType Directory -Force

# Clone repository (if using Git) or copy files
cd C:\inetpub\wwwroot
git clone https://github.com/samerc/snoozer.git

# Or copy files manually to C:\inetpub\wwwroot\snoozer
```

### Set Permissions

```powershell
# Grant IIS_IUSRS read access
icacls "C:\inetpub\wwwroot\snoozer" /grant "IIS_IUSRS:(OI)(CI)RX" /T

# Grant IUSR read access
icacls "C:\inetpub\wwwroot\snoozer" /grant "IUSR:(OI)(CI)RX" /T

# Grant write access to logs directory (if needed)
New-Item -Path "C:\inetpub\wwwroot\snoozer\logs" -ItemType Directory -Force
icacls "C:\inetpub\wwwroot\snoozer\logs" /grant "IIS_IUSRS:(OI)(CI)M" /T
```

## 7. Configure Environment Variables

```powershell
# Copy environment template
Copy-Item "C:\inetpub\wwwroot\snoozer\.env.example" "C:\inetpub\wwwroot\snoozer\.env"

# Edit .env file
notepad "C:\inetpub\wwwroot\snoozer\.env"
```

Set your values in `.env`:

```env
DB_HOST="localhost"
DB_USER="snoozeradmin"
DB_PASS="YOUR_SECURE_PASSWORD"
DB_NAME="snoozer-app"

# Option 1: IMAP (use with EmailIngestor.php)
IMAP_SERVER="{mail.yourdomain.com:993/imap/ssl}"
IMAP_USER="catch@yourdomain.com"
IMAP_PASS="YOUR_IMAP_PASSWORD"

# Option 2: Mailgun API (use with EmailIngestorMailgun.php)
MAILGUN_API_KEY="key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
MAILGUN_DOMAIN="yourdomain.com"

APP_URL="https://app.yourdomain.com"
MAIL_DOMAIN="yourdomain.com"

# Logging
LOG_FILE="C:\inetpub\logs\snoozer\app.log"
```

### Secure .env File

```powershell
# Remove inheritance and set explicit permissions
icacls "C:\inetpub\wwwroot\snoozer\.env" /inheritance:r
icacls "C:\inetpub\wwwroot\snoozer\.env" /grant:r "IIS_IUSRS:(R)"
icacls "C:\inetpub\wwwroot\snoozer\.env" /grant:r "Administrators:(F)"
```

## 8. Create IIS Website

### Using IIS Manager GUI

1. Open IIS Manager (`inetmgr`)
2. Right-click "Sites" → "Add Website"
3. Configure:
   - **Site name**: Snoozer
   - **Physical path**: `C:\inetpub\wwwroot\snoozer`
   - **Binding**: HTTP, Port 80, Host name: `app.yourdomain.com`
4. Click OK

### Using PowerShell

```powershell
# Remove default website
Remove-WebSite -Name "Default Web Site"

# Create new website
New-WebSite -Name "Snoozer" -Port 80 -PhysicalPath "C:\inetpub\wwwroot\snoozer" -HostHeader "app.yourdomain.com"

# Start the website
Start-WebSite -Name "Snoozer"
```

## 9. Configure web.config for URL Rewriting

Update `C:\inetpub\wwwroot\snoozer\web.config`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <directoryBrowse enabled="false" />
        
        <!-- Default document -->
        <defaultDocument>
            <files>
                <clear />
                <add value="index.php" />
                <add value="login.php" />
            </files>
        </defaultDocument>
        
        <!-- URL Rewrite rules -->
        <rewrite>
            <rules>
                <!-- Redirect to login.php if no file specified -->
                <rule name="Root to Login" stopProcessing="true">
                    <match url="^$" />
                    <action type="Redirect" url="login.php" redirectType="Temporary" />
                </rule>
            </rules>
        </rewrite>
        
        <!-- Security: Block access to sensitive files -->
        <security>
            <requestFiltering>
                <hiddenSegments>
                    <add segment=".env" />
                    <add segment=".git" />
                    <add segment=".gitignore" />
                    <add segment="migrations" />
                </hiddenSegments>
                <fileExtensions>
                    <add fileExtension=".env" allowed="false" />
                </fileExtensions>
            </requestFiltering>
        </security>
        
        <!-- Error pages -->
        <httpErrors errorMode="Detailed" />
        
        <!-- Compression -->
        <urlCompression doStaticCompression="true" doDynamicCompression="true" />
    </system.webServer>
</configuration>
```

## 10. Install SSL Certificate

### Option A: Let's Encrypt (Free)

1. Install Win-ACME:
   - Download from: https://www.win-acme.com/
   - Extract to `C:\Tools\win-acme`

2. Run Win-ACME:

```powershell
cd C:\Tools\win-acme
.\wacs.exe
```

3. Follow the wizard:
   - Choose "N" for new certificate
   - Select your IIS site
   - Choose validation method (HTTP-01 recommended)
   - Certificate will be installed automatically

### Option B: Commercial Certificate

1. Generate Certificate Request in IIS Manager:
   - Open IIS Manager
   - Click server name → "Server Certificates"
   - "Create Certificate Request"
   - Fill in details and save CSR file

2. Purchase certificate from CA (DigiCert, Sectigo, etc.)

3. Complete Certificate Request:
   - "Complete Certificate Request" in IIS
   - Import received certificate

### Bind SSL Certificate

```powershell
# Add HTTPS binding
New-WebBinding -Name "Snoozer" -Protocol https -Port 443 -HostHeader "app.yourdomain.com" -SslFlags 1

# Bind certificate (replace thumbprint with your certificate's thumbprint)
$cert = Get-ChildItem -Path Cert:\LocalMachine\My | Where-Object {$_.Subject -like "*app.yourdomain.com*"}
New-Item -Path "IIS:\SslBindings\0.0.0.0!443!app.yourdomain.com" -Value $cert
```

### Force HTTPS Redirect

Add to `web.config` inside `<rewrite><rules>`:

```xml
<rule name="Force HTTPS" stopProcessing="true">
    <match url="(.*)" />
    <conditions>
        <add input="{HTTPS}" pattern="off" />
    </conditions>
    <action type="Redirect" url="https://{HTTP_HOST}/{R:1}" redirectType="Permanent" />
</rule>
```

## 11. Configure Windows Task Scheduler for Cron Job

The cron job processes incoming emails and sends due reminders.

```powershell
# Create log directory
New-Item -Path "C:\inetpub\logs\snoozer" -ItemType Directory -Force

# Create scheduled task
$action = New-ScheduledTaskAction -Execute "C:\PHP\php.exe" -Argument "C:\inetpub\wwwroot\snoozer\cron.php" -WorkingDirectory "C:\inetpub\wwwroot\snoozer"
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration ([TimeSpan]::MaxValue)
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -RunOnlyIfNetworkAvailable

Register-ScheduledTask -TaskName "Snoozer Cron Job" -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Description "Processes emails and sends reminders for Snoozer app"
```

### Verify Task

```powershell
# Check task status
Get-ScheduledTask -TaskName "Snoozer Cron Job"

# Run task manually for testing
Start-ScheduledTask -TaskName "Snoozer Cron Job"

# View task history
Get-ScheduledTaskInfo -TaskName "Snoozer Cron Job"
```

## 12. Configure Windows Firewall

```powershell
# Allow HTTP traffic
New-NetFirewallRule -DisplayName "Allow HTTP" -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow

# Allow HTTPS traffic
New-NetFirewallRule -DisplayName "Allow HTTPS" -Direction Inbound -Protocol TCP -LocalPort 443 -Action Allow

# View firewall rules
Get-NetFirewallRule | Where-Object {$_.DisplayName -like "*HTTP*"}
```

## 13. Email Server Setup

Snoozer requires an IMAP mailbox to receive emails. Options:

### Option A: Use Existing Mail Server
Configure your mail server to:
1. Create a catch-all mailbox (e.g., `catch@snoozer.cloud`)
2. Forward all `*@snoozer.cloud` to this mailbox
3. Update `.env` with IMAP credentials

### Option B: Use Mailgun API
See [MAILGUN_SETUP.md](MAILGUN_SETUP.md) for detailed instructions on configuring Mailgun.

### IMAP Connection String Examples
```env
# Standard IMAP with SSL (port 993)
IMAP_SERVER="{mail.example.com:993/imap/ssl}"

# IMAP without SSL validation (testing only)
IMAP_SERVER="{mail.example.com:993/imap/ssl/novalidate-cert}"

# Gmail IMAP
IMAP_SERVER="{imap.gmail.com:993/imap/ssl}"

# Office 365 IMAP
IMAP_SERVER="{outlook.office365.com:993/imap/ssl}"
```

## 14. Verify Installation

### Test PHP

```powershell
# Create phpinfo file
"<?php phpinfo(); ?>" | Out-File -FilePath "C:\inetpub\wwwroot\snoozer\phpinfo.php" -Encoding ASCII

# Visit https://app.yourdomain.com/phpinfo.php
# Delete after verification for security
Remove-Item "C:\inetpub\wwwroot\snoozer\phpinfo.php"
```

### Test Application

1. Visit `https://app.yourdomain.com/login.php`
2. Check IIS logs: `C:\inetpub\logs\LogFiles\W3SVC1\`
3. Check PHP error log: `C:\inetpub\logs\php_errors.log`
4. Check cron logs: `C:\inetpub\logs\snoozer\`

### Test Cron Manually

```powershell
cd C:\inetpub\wwwroot\snoozer
php cron.php
```

## 15. Create Admin User

Connect to MySQL and create an admin user:

```powershell
mysql -u snoozeradmin -p snoozer-app
```

```sql
-- Generate password hash first
-- Run in PowerShell: php -r "echo password_hash('YourPassword123', PASSWORD_DEFAULT);"

INSERT INTO users (name, email, password, role)
VALUES ('Admin', 'admin@yourdomain.com', '$2y$10$YOUR_HASH_HERE', 'admin');
```

Or use PowerShell to generate and insert:

```powershell
# Generate password hash
$password = "YourPassword123"
$hash = php -r "echo password_hash('$password', PASSWORD_DEFAULT);"

# Insert into database
mysql -u snoozeradmin -p snoozer-app -e "INSERT INTO users (name, email, password, role) VALUES ('Admin', 'admin@yourdomain.com', '$hash', 'admin');"
```

## Troubleshooting

### PHP Not Processing (Downloads Instead)

```powershell
# Verify PHP handler is registered
Get-WebHandler -Name "PHP-FastCGI"

# Re-register if needed
Remove-WebHandler -Name "PHP-FastCGI"
New-WebHandler -Name "PHP-FastCGI" -Path "*.php" -Verb "*" -Modules "FastCgiModule" -ScriptProcessor "C:\PHP\php-cgi.exe" -ResourceType File
```

### IMAP Connection Failed

```powershell
# Test IMAP from command line
php -r "var_dump(imap_open('{mail.example.com:993/imap/ssl}', 'user', 'pass'));"

# Check if IMAP extension is loaded
php -m | Select-String -Pattern "imap"

# Enable IMAP extension in php.ini if missing
# Uncomment: extension=imap
# Restart IIS
iisreset
```

### Permission Denied Errors

```powershell
# Reset permissions
icacls "C:\inetpub\wwwroot\snoozer" /reset /T

# Grant proper access
icacls "C:\inetpub\wwwroot\snoozer" /grant "IIS_IUSRS:(OI)(CI)RX" /T
icacls "C:\inetpub\wwwroot\snoozer" /grant "IUSR:(OI)(CI)RX" /T

# Grant write access to logs
icacls "C:\inetpub\wwwroot\snoozer\logs" /grant "IIS_IUSRS:(OI)(CI)M" /T
```

### Database Connection Failed

```powershell
# Test MySQL connection
php -r "new mysqli('localhost', 'snoozeradmin', 'password', 'snoozer-app');"

# Check MySQL service
Get-Service -Name MySQL*

# Start MySQL if stopped
Start-Service -Name MySQL80
```

### 500 Internal Server Error

```powershell
# Enable detailed errors temporarily
# Edit web.config: <httpErrors errorMode="Detailed" />

# Check PHP error log
Get-Content "C:\inetpub\logs\php_errors.log" -Tail 50

# Check IIS logs
Get-Content "C:\inetpub\logs\LogFiles\W3SVC1\*.log" -Tail 50
```

### Scheduled Task Not Running

```powershell
# Check task status
Get-ScheduledTask -TaskName "Snoozer Cron Job" | Select-Object State, LastRunTime, LastTaskResult

# View task history in Event Viewer
Get-WinEvent -LogName "Microsoft-Windows-TaskScheduler/Operational" -MaxEvents 20 | Where-Object {$_.Message -like "*Snoozer*"}

# Run manually with output
cd C:\inetpub\wwwroot\snoozer
php cron.php > C:\inetpub\logs\snoozer\manual_run.log 2>&1
```

## Security Checklist

- [ ] `.env` file not accessible via web (403 Forbidden)
- [ ] Database uses strong password
- [ ] SSL certificate installed and working
- [ ] HTTPS redirect enabled
- [ ] Windows Firewall configured (only ports 80, 443 open)
- [ ] File permissions correct (IIS_IUSRS read-only except logs)
- [ ] PHP `display_errors` disabled in production
- [ ] Directory browsing disabled
- [ ] Sensitive directories blocked in web.config
- [ ] Regular Windows Updates enabled
- [ ] Regular backups configured for database

## Performance Optimization

### Enable Output Caching

Add to `web.config`:

```xml
<system.webServer>
    <caching enabled="true" enableKernelCache="true">
        <profiles>
            <add extension=".php" policy="CacheUntilChange" kernelCachePolicy="CacheUntilChange" />
            <add extension=".css" policy="CacheUntilChange" kernelCachePolicy="CacheUntilChange" />
            <add extension=".js" policy="CacheUntilChange" kernelCachePolicy="CacheUntilChange" />
        </profiles>
    </caching>
</system.webServer>
```

### Enable OPcache

Add to `php.ini`:

```ini
[opcache]
zend_extension=opcache
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
```

Restart IIS:

```powershell
iisreset
```

## Backup Strategy

### Database Backup Script

Create `C:\Scripts\snoozer-backup.ps1`:

```powershell
# Snoozer Database Backup Script
$BackupDir = "C:\Backups\Snoozer"
$Date = Get-Date -Format "yyyyMMdd_HHmmss"
$BackupFile = "$BackupDir\snoozer_db_$Date.sql"

# Create backup directory if it doesn't exist
New-Item -Path $BackupDir -ItemType Directory -Force | Out-Null

# Backup database
& "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe" -u snoozeradmin -p'YOUR_PASSWORD' snoozer-app > $BackupFile

# Compress backup
Compress-Archive -Path $BackupFile -DestinationPath "$BackupFile.zip"
Remove-Item $BackupFile

# Keep only last 7 days of backups
Get-ChildItem -Path $BackupDir -Filter "*.zip" | Where-Object {$_.LastWriteTime -lt (Get-Date).AddDays(-7)} | Remove-Item

Write-Host "Backup completed: $BackupFile.zip"
```

### Schedule Backup

```powershell
# Create scheduled task for daily backup at 2 AM
$action = New-ScheduledTaskAction -Execute "PowerShell.exe" -Argument "-ExecutionPolicy Bypass -File C:\Scripts\snoozer-backup.ps1"
$trigger = New-ScheduledTaskTrigger -Daily -At 2am
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest

Register-ScheduledTask -TaskName "Snoozer Database Backup" -Action $action -Trigger $trigger -Principal $principal -Description "Daily backup of Snoozer database"
```

## Monitoring

### Enable Application Insights (Optional)

1. Install Application Request Routing (ARR)
2. Configure Failed Request Tracing in IIS
3. Set up Windows Performance Monitor for:
   - CPU usage
   - Memory usage
   - IIS request queue length
   - PHP-CGI process count

### Log Rotation

Create `C:\Scripts\rotate-logs.ps1`:

```powershell
# Rotate Snoozer logs
$LogDir = "C:\inetpub\logs\snoozer"
$ArchiveDir = "$LogDir\archive"
$DaysToKeep = 30

New-Item -Path $ArchiveDir -ItemType Directory -Force | Out-Null

Get-ChildItem -Path $LogDir -Filter "*.log" | Where-Object {$_.LastWriteTime -lt (Get-Date).AddDays(-1)} | ForEach-Object {
    $ArchiveName = "$ArchiveDir\$($_.BaseName)_$(Get-Date $_.LastWriteTime -Format 'yyyyMMdd').log.zip"
    Compress-Archive -Path $_.FullName -DestinationPath $ArchiveName
    Remove-Item $_.FullName
}

# Delete old archives
Get-ChildItem -Path $ArchiveDir -Filter "*.zip" | Where-Object {$_.LastWriteTime -lt (Get-Date).AddDays(-$DaysToKeep)} | Remove-Item
```

## Additional Resources

- [IIS Documentation](https://docs.microsoft.com/en-us/iis/)
- [PHP on Windows](https://windows.php.net/)
- [MySQL Documentation](https://dev.mysql.com/doc/)
- [Win-ACME Documentation](https://www.win-acme.com/reference/cli)

## Support

For issues specific to this deployment guide, please refer to the main [DEPLOYMENT.md](DEPLOYMENT.md) for general application configuration or consult the project repository.
