# Mailgun + Postfix Setup Guide for Snoozer

This guide configures Mailgun for both sending and receiving emails on `snoozeme.app`, with Postfix as the local mail relay.

## Architecture Overview

```
SENDING (Outbound):
Your App → Postfix → Mailgun SMTP (port 587) → Recipient

RECEIVING (Inbound):
Sender → Mailgun MX → Mailgun Storage → Your App (via API polling)
```

---

## Part 1: Mailgun DNS Configuration

### 1.1 Verify Domain in Mailgun

1. Log in to Mailgun Dashboard → **Sending** → **Domains**
2. Ensure `snoozeme.app` is verified (green checkmark)
3. If not verified, add the required DNS records:
   - TXT record for domain verification
   - TXT record for SPF
   - DKIM records (two TXT records)

### 1.2 Configure MX Records for Receiving

In your DNS provider, add MX records to route incoming mail to Mailgun:

| Type | Host | Priority | Value |
|------|------|----------|-------|
| MX | @ | 10 | mxa.mailgun.org |
| MX | @ | 10 | mxb.mailgun.org |

**Important**: Remove any existing MX records for `snoozeme.app` first.

### 1.3 Verify DNS Records

```bash
# Check MX records
dig MX snoozeme.app +short

# Expected output:
# 10 mxa.mailgun.org.
# 10 mxb.mailgun.org.

# Check SPF
dig TXT snoozeme.app +short | grep spf

# Check DKIM
dig TXT smtp._domainkey.snoozeme.app +short
```

---

## Part 2: Mailgun Inbound Routes

### 2.1 Create a Catch-All Route

1. Go to Mailgun Dashboard → **Receiving** → **Routes**
2. Click **Create Route**
3. Configure:

| Field | Value |
|-------|-------|
| Expression Type | Match Recipient |
| Recipient | `.*@snoozeme.app` (regex for catch-all) |
| Actions | **Store and notify** → Check "Store a copy" |
| Priority | 0 |
| Description | Snoozer catch-all route |

4. Click **Create Route**

### 2.2 Get API Credentials

1. Go to Mailgun Dashboard → **Settings** → **API Security**
2. Note your **Private API Key** (starts with `key-...`)
3. Also note your **Domain** sending key if different

---

## Part 3: Configure Postfix for Outbound Relay

### 3.1 Install Postfix

```bash
sudo apt update
sudo apt install -y postfix libsasl2-modules

# During installation, select:
# - General type: Internet Site
# - System mail name: snoozeme.app
```

### 3.2 Configure Postfix Main Settings

```bash
sudo nano /etc/postfix/main.cf
```

Replace or add these settings:

```ini
# Basic settings
myhostname = mail.snoozeme.app
mydomain = snoozeme.app
myorigin = $mydomain
mydestination = localhost

# Mailgun relay configuration
relayhost = [smtp.mailgun.org]:587
smtp_sasl_auth_enable = yes
smtp_sasl_password_maps = hash:/etc/postfix/sasl_passwd
smtp_sasl_security_options = noanonymous
smtp_tls_security_level = encrypt
smtp_tls_CAfile = /etc/ssl/certs/ca-certificates.crt

# Additional security
smtpd_tls_security_level = may
smtp_tls_note_starttls_offer = yes
```

### 3.3 Create SASL Password File

```bash
sudo nano /etc/postfix/sasl_passwd
```

Add your Mailgun SMTP credentials:

```
[smtp.mailgun.org]:587 postmaster@snoozeme.app:YOUR_MAILGUN_SMTP_PASSWORD
```

**Note**: Find SMTP credentials in Mailgun → Domain Settings → SMTP Credentials

Secure and hash the file:

```bash
sudo chmod 600 /etc/postfix/sasl_passwd
sudo postmap /etc/postfix/sasl_passwd
sudo chmod 600 /etc/postfix/sasl_passwd.db
```

### 3.4 Restart Postfix

```bash
sudo systemctl restart postfix
sudo systemctl enable postfix
```

### 3.5 Test Outbound Email

```bash
echo "Test email from Snoozer server" | mail -s "Test Subject" your-email@gmail.com
```

Check Mailgun logs: Dashboard → **Logs** → **Messages**

---

## Part 4: Update Snoozer Application

### 4.1 Update Environment Variables

Edit `/var/www/snoozer/.env`:

```env
# Database (unchanged)
DB_HOST="localhost"
DB_USER="snoozeradmin"
DB_PASS="your_password"
DB_NAME="snoozer-app"

# Mailgun API for receiving emails
MAILGUN_API_KEY="key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
MAILGUN_DOMAIN="snoozeme.app"

# App URL
APP_URL="https://app.snoozeme.app"
```

### 4.2 Update EmailIngestor.php

Replace the IMAP-based ingestor with Mailgun API polling.

**Backup original:**
```bash
cp /var/www/snoozer/src/EmailIngestor.php /var/www/snoozer/src/EmailIngestor.php.bak
```

**Replace with new version** (see code changes in next section)

### 4.3 Update .env.example

Add the new Mailgun variables for documentation:

```env
# Mailgun API for receiving emails
MAILGUN_API_KEY="key-your-api-key"
MAILGUN_DOMAIN="snoozeme.app"
```

---

## Part 5: Switch to Mailgun Email Ingestor

A Mailgun-compatible `EmailIngestorMailgun.php` is included in the repository.

### 5.1 Backup and Replace

```bash
cd /var/www/snoozer/src

# Backup the original IMAP-based ingestor
cp EmailIngestor.php EmailIngestor.imap.php.bak

# Replace with Mailgun version
cp EmailIngestorMailgun.php EmailIngestor.php
```

### 5.2 How It Works

The Mailgun ingestor:

1. Calls Mailgun Events API to find "stored" messages
2. Fetches full message content from storage URL
3. Parses sender, recipient, subject, and headers
4. Creates user if new (sends welcome email)
5. Stores email in database for processing
6. Deletes message from Mailgun storage

Key Mailgun API endpoints used:
- `GET /v3/{domain}/events?event=stored` - List stored messages
- `GET {storage.url}` - Fetch full message
- `DELETE {storage.url}` - Delete after processing

### 5.3 Reverting to IMAP

To switch back to IMAP:

```bash
cd /var/www/snoozer/src
cp EmailIngestor.imap.php.bak EmailIngestor.php
```

Then update `.env` with IMAP credentials instead of Mailgun.

---

## Part 6: Verify Complete Setup

### 6.1 Test Outbound (Sending)

```bash
# From server command line
echo "Outbound test" | mail -s "Test" yourpersonal@email.com

# Check Mailgun Dashboard → Logs
```

### 6.2 Test Inbound (Receiving)

1. Send an email to `test@snoozeme.app`
2. Check Mailgun Dashboard → **Logs** → Look for "stored" event
3. Run cron manually:
   ```bash
   sudo -u www-data php /var/www/snoozer/cron.php
   ```
4. Check application logs for processing

### 6.3 Test Full Flow

1. Send email to `tomorrow@snoozeme.app`
2. Wait for cron (or run manually)
3. Check dashboard for the scheduled reminder

---

## Troubleshooting

### Postfix Not Sending

```bash
# Check Postfix queue
mailq

# Check logs
sudo tail -f /var/log/mail.log

# Test SMTP connection
openssl s_client -starttls smtp -connect smtp.mailgun.org:587
```

### Mailgun Not Receiving

1. Verify MX records are correct: `dig MX snoozeme.app`
2. Check Mailgun Dashboard → Logs for incoming events
3. Verify Route is configured correctly (catch-all pattern)

### API Authentication Failed

```bash
# Test Mailgun API
curl -s --user "api:YOUR_API_KEY" \
    "https://api.mailgun.net/v3/snoozeme.app/events?limit=1"
```

### Stored Messages Not Found

1. Ensure Route action includes "Store and notify"
2. Check Mailgun → Receiving → Routes is active
3. Messages are stored for 3 days by default

---

## Security Notes

- Keep `MAILGUN_API_KEY` secure (600 permissions on .env)
- Mailgun API key has full access - consider using domain-specific keys
- Enable Mailgun's webhook signing for additional security
- Monitor Mailgun logs for suspicious activity

---

## Mailgun Pricing Note

- **Flex (Pay as you go)**: First 1,000 emails free, then $1 per 1,000
- **Foundation ($35/mo)**: 50,000 emails included
- Inbound email storage is included in all plans
- Stored messages retained for 3 days

---

## Quick Reference

| Service | Endpoint/Port |
|---------|---------------|
| Mailgun SMTP | smtp.mailgun.org:587 |
| Mailgun API | api.mailgun.net |
| MX Primary | mxa.mailgun.org |
| MX Secondary | mxb.mailgun.org |
