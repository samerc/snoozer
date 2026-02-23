# Snoozer Deployment Guide - Ubuntu Server

This guide covers deploying Snoozer on Ubuntu 22.04/24.04 LTS with Apache, MySQL, and PHP.

## Prerequisites

- Ubuntu 22.04 or 24.04 LTS
- Root or sudo access
- Domain name pointing to your server (e.g., `app.snoozer.cloud`)

## 1. System Update

```bash
sudo apt update && sudo apt upgrade -y
```

## 2. Install PHP and Required Extensions

```bash
sudo apt install -y php php-mysql php-imap php-mbstring php-xml php-curl libapache2-mod-php
```

Verify installation:
```bash
php -v
php -m | grep -E "(mysqli|imap|mbstring|openssl)"
```

## 3. Install MySQL Server

```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation
```

Create database and user:
```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE `snoozer-app` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'snoozeradmin'@'localhost' IDENTIFIED BY 'YOUR_SECURE_PASSWORD';
GRANT ALL PRIVILEGES ON `snoozer-app`.* TO 'snoozeradmin'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Import schema:
```bash
mysql -u snoozeradmin -p snoozer-app < /var/www/snoozer/Database_Export.sql
```

## 4. Install Apache Web Server

```bash
sudo apt install -y apache2
sudo a2enmod rewrite
sudo systemctl enable apache2
```

## 5. Deploy Application Files

```bash
# Create web directory
sudo mkdir -p /var/www/snoozer

# Clone repository (or upload files)
cd /var/www
sudo git clone https://github.com/samerc/snoozer.git

# Set ownership
sudo chown -R www-data:www-data /var/www/snoozer
sudo chmod -R 755 /var/www/snoozer
```

## 6. Configure Environment

```bash
sudo cp /var/www/snoozer/.env.example /var/www/snoozer/.env
sudo nano /var/www/snoozer/.env
```

Set your values:
```env
DB_HOST="localhost"
DB_USER="snoozeradmin"
DB_PASS="YOUR_SECURE_PASSWORD"
DB_NAME="snoozer-app"

IMAP_SERVER="{mail.yourdomain.com:993/imap/ssl}"
IMAP_USER="catch@yourdomain.com"
IMAP_PASS="YOUR_IMAP_PASSWORD"

APP_URL="https://app.yourdomain.com"
```

Secure the file:
```bash
sudo chmod 600 /var/www/snoozer/.env
sudo chown www-data:www-data /var/www/snoozer/.env
```

## 7. Configure Apache Virtual Host

```bash
sudo nano /etc/apache2/sites-available/snoozer.conf
```

```apache
<VirtualHost *:80>
    ServerName app.yourdomain.com
    DocumentRoot /var/www/snoozer

    <Directory /var/www/snoozer>
        AllowOverride All
        Require all granted
    </Directory>

    # Block access to sensitive files
    <FilesMatch "^\.env$">
        Require all denied
    </FilesMatch>

    <FilesMatch "^\.git">
        Require all denied
    </FilesMatch>

    ErrorLog ${APACHE_LOG_DIR}/snoozer_error.log
    CustomLog ${APACHE_LOG_DIR}/snoozer_access.log combined
</VirtualHost>
```

Enable the site:
```bash
sudo a2ensite snoozer.conf
sudo a2dissite 000-default.conf
sudo systemctl reload apache2
```

## 8. Install SSL Certificate (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d app.yourdomain.com
```

Auto-renewal is configured automatically. Test with:
```bash
sudo certbot renew --dry-run
```

## 9. Configure Cron Job

The cron job processes incoming emails and sends due reminders.

```bash
sudo crontab -u www-data -e
```

Add this line:
```cron
* * * * * /usr/bin/php /var/www/snoozer/cron.php >> /var/log/snoozer/cron.log 2>&1
```

Create log directory:
```bash
sudo mkdir -p /var/log/snoozer
sudo chown www-data:www-data /var/log/snoozer
```

## 10. Configure Firewall

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Apache Full'
sudo ufw enable
sudo ufw status
```

## 11. PHP Configuration (Optional Tuning)

```bash
sudo nano /etc/php/8.*/apache2/php.ini
```

Recommended settings:
```ini
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 120
memory_limit = 256M
date.timezone = UTC
```

Restart Apache:
```bash
sudo systemctl restart apache2
```

## 12. Email Server Setup

Snoozer requires an IMAP mailbox to receive emails. Options:

### Option A: Use Existing Mail Server
Configure your mail server to:
1. Create a catch-all mailbox (e.g., `catch@snoozer.cloud`)
2. Forward all `*@snoozer.cloud` to this mailbox
3. Update `.env` with IMAP credentials

### Option B: Use External Service
Services like Mailgun, SendGrid, or Zoho Mail can provide IMAP access.

### IMAP Connection String Examples
```env
# Standard IMAP with SSL (port 993)
IMAP_SERVER="{mail.example.com:993/imap/ssl}"

# IMAP without SSL validation (testing only)
IMAP_SERVER="{mail.example.com:993/imap/ssl/novalidate-cert}"

# Plain IMAP (port 143, not recommended)
IMAP_SERVER="{mail.example.com:143/imap}"
```

## 13. Verify Installation

1. Visit `https://app.yourdomain.com/login.php`
2. Check Apache logs: `sudo tail -f /var/log/apache2/snoozer_error.log`
3. Check cron logs: `sudo tail -f /var/log/snoozer/cron.log`
4. Test cron manually: `sudo -u www-data php /var/www/snoozer/cron.php`

## 14. Create Admin User

Connect to MySQL and create an admin user:
```bash
sudo mysql -u snoozeradmin -p snoozer-app
```

```sql
INSERT INTO users (name, email, password, role)
VALUES ('Admin', 'admin@yourdomain.com', '$2y$10$HASH_HERE', 'admin');
```

Generate password hash:
```bash
php -r "echo password_hash('YourPassword123', PASSWORD_DEFAULT);"
```

## Troubleshooting

### IMAP Connection Failed
```bash
# Test IMAP from command line
php -r "var_dump(imap_open('{mail.example.com:993/imap/ssl}', 'user', 'pass'));"
```

### Permission Denied Errors
```bash
sudo chown -R www-data:www-data /var/www/snoozer
sudo find /var/www/snoozer -type d -exec chmod 755 {} \;
sudo find /var/www/snoozer -type f -exec chmod 644 {} \;
sudo chmod 600 /var/www/snoozer/.env
```

### Cron Not Running
```bash
# Check cron service
sudo systemctl status cron

# Check cron logs
grep CRON /var/log/syslog | tail -20

# Run manually with output
sudo -u www-data php /var/www/snoozer/cron.php
```

### PHP Extensions Missing
```bash
# List installed extensions
php -m

# Install missing extension
sudo apt install php-imap
sudo systemctl restart apache2
```

## Security Checklist

- [ ] `.env` file not accessible via web (403 Forbidden)
- [ ] Database uses strong password
- [ ] SSL certificate installed and working
- [ ] Firewall enabled (UFW)
- [ ] File permissions correct (www-data ownership)
- [ ] PHP `display_errors` disabled in production
- [ ] Regular backups configured for database

## Backup Script

Create `/usr/local/bin/snoozer-backup.sh`:
```bash
#!/bin/bash
BACKUP_DIR="/var/backups/snoozer"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u snoozeradmin -p'YOUR_PASSWORD' snoozer-app > $BACKUP_DIR/db_$DATE.sql

# Compress
gzip $BACKUP_DIR/db_$DATE.sql

# Keep last 7 days
find $BACKUP_DIR -name "*.gz" -mtime +7 -delete
```

Add to cron:
```bash
0 2 * * * /usr/local/bin/snoozer-backup.sh
```
