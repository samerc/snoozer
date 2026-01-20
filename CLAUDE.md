# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Snoozer is a PHP-based email reminder application. Users send emails to special addresses (e.g., `tomorrow@snoozer.cloud`, `2hours@snoozer.cloud`) and receive reminders at the specified time. The system processes incoming emails via IMAP, calculates action timestamps, and sends reminders when due.

## Development Commands

```bash
# Run locally with PHP built-in server
php -S localhost:8000

# Run scheduled processing (normally via cron every minute)
php cron.php

# Test workflow simulation
php tests/simulate_flow.php

# Test logic
php tests/test_logic.php

# Import database schema
mysql -u root -p < Database_Export.sql
```

## Architecture

### Core Classes (src/)

- **Database.php** - MySQL singleton connection with prepared statements and transaction support
- **User.php** - User CRUD, authentication (bcrypt), password management
- **Session.php** - Secure session management (timeout, secure cookies, security headers)
- **AuditLog.php** - Admin action logging (user changes, password resets, template updates, logins)
- **EmailIngestor.php** - IMAP mailbox connection, email parsing with transaction safety
- **EmailIngestorMailgun.php** - Alternative ingestor using Mailgun API (for environments without IMAP)
- **EmailProcessor.php** - Main business logic: interprets time expressions, sends reminders and NDRs
- **EmailRepository.php** - Data access layer for emails table
- **EmailStatus.php** - Constants for email processing states (UNPROCESSED, PROCESSED, REMINDED, IGNORED)
- **Mailer.php** - PHP mail() wrapper with HTML support and threading headers
- **RateLimiter.php** - Login brute force protection (tracks failed attempts per IP)
- **Utils.php** - CSRF helpers, email validation, time parsing, encryption utilities

### Request Flow

1. Web pages (login.php, dashboard.php, etc.) authenticate via sessions
2. Background processing via `cron.php` runs EmailIngestor then EmailProcessor
3. Action URLs (snooze/cancel) handled by `actions/exec.php`
4. API endpoints in `api/` for AJAX operations

### Email Status States

- `processed = NULL/0` - Unprocessed, waiting for analysis
- `processed = 1` - Processed, waiting for action timestamp
- `processed = 2` - Reminded, action sent to user

### Time Expression Parsing

EmailProcessor interprets addresses like:
- `tomorrow`, `monday`, `8am` - Day/time aliases
- `2hours`, `1week` - Relative durations
- `check`, `search`, `upcoming`, `set` - Special commands

## Configuration

Environment variables in `.env`:
- `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` - MySQL connection
- `IMAP_SERVER`, `IMAP_USER`, `IMAP_PASS` - Email ingestion (IMAP mode)
- `MAILGUN_API_KEY`, `MAILGUN_DOMAIN` - Email ingestion (Mailgun mode)
- `APP_URL` - Base URL for web app (e.g., `https://app.snoozeme.app`)
- `MAIL_DOMAIN` - Domain for snoozer email addresses (e.g., `snoozeme.app`)

## Database Tables

- `users` - User accounts with bcrypt passwords, timezone, theme preferences
- `emails` - Reminder queue with message_id, timestamps, processing status
- `email_templates` - Customizable email templates (wrapper, reminder)
- `emailCategory` - Kanban board categories
- `login_attempts` - Rate limiting tracking for brute force protection
- `audit_logs` - Admin action audit trail (user changes, password resets, logins)

## Security

- **CSRF Protection** - All forms use `Utils::csrfField()` and validate with `Utils::validateCsrfToken()`. AJAX endpoints accept `X-CSRF-Token` header via `Utils::validateAjaxCsrf()`
- **Session Management** - `Session::start()` handles secure cookie settings (HttpOnly, Secure, SameSite=Lax), 30-minute inactivity timeout, and session ID regeneration after login
- **Security Headers** - Automatically sent via `Session::sendSecurityHeaders()`: X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, CSP, Referrer-Policy, Permissions-Policy
- **Password Hashing** - bcrypt via `password_hash()` / `password_verify()`
- **SQL Injection** - Prepared statements throughout via Database class
- **Action URL Security** - AES-256-CBC encrypted message IDs with per-email SSL keys
- **Rate Limiting** - Login attempts limited to 5 per 15 minutes per IP via RateLimiter class
- **Audit Logging** - Admin actions logged via AuditLog class (user CRUD, password resets, template changes, logins)
- **Transaction Safety** - Email ingestion uses database transactions to prevent data loss

## Key Technical Notes

- No PHP framework - vanilla PHP with custom class structure
- Frontend uses Bootstrap 4.2.1 with dark/light theme support
- Action URLs use `APP_URL` env variable (configurable per environment)
- Windows deployment via IIS (web.config present)
