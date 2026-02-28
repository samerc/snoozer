# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Snoozer is a PHP-based email reminder application. Users send emails to special addresses (e.g., `tomorrow@snoozer.cloud`, `2hours@snoozer.cloud`) and receive reminders at the specified time. The system processes incoming emails via IMAP, calculates action timestamps, and sends reminders when due. Users can also create and manage reminders directly from the web dashboard.

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

# Apply a migration (run from project root)
mysql -u root -p snoozer < migrations/008_add_notes.sql

# Migrate legacy emails from CSV (export filtered CSV from HeidiSQL first — see cleanup/migrate_emails.php header)
php cleanup/migrate_emails.php
```

## Architecture

### Core Classes (src/)

- **Database.php** - MySQL singleton connection with prepared statements and transaction support
- **User.php** - User CRUD, authentication (bcrypt), password management, token-based password setup
- **Session.php** - Secure session management (timeout, secure cookies, security headers); `Session::isLoggedIn()` is public and used directly by AJAX endpoints instead of `requireAuth()` to avoid redirect-on-fail with empty body
- **AuditLog.php** - Admin action logging; has `getLogs($filters, $limit, $offset)` and `countLogs($filters)` for the audit viewer
- **EmailIngestor.php** - IMAP mailbox connection, email parsing with transaction safety
- **EmailIngestorMailgun.php** - Alternative ingestor using Mailgun API (for environments without IMAP)
- **EmailProcessor.php** - Main business logic: interprets time expressions, sends reminders and NDRs; looks up `DefaultReminderTime` per user and passes it to `Utils::parseTimeExpression()`; injects `{{NOTES_BLOCK}}` into reminder emails when a note is set
- **EmailRepository.php** - Data access layer for emails table; `getUpcomingForUser` selects the `notes` column; has stat methods (`countDueTodayForUser`, `countDueThisWeekForUser`, `countOverdueForUser`) and optional `$subject` search filter
- **EmailStatus.php** - Constants for email processing states (UNPROCESSED, PROCESSED, REMINDED, IGNORED)
- **Mailer.php** - PHP mail() wrapper with HTML support and threading headers
- **RateLimiter.php** - Login brute force protection (tracks failed attempts per IP)
- **Logger.php** - Structured logging with levels (DEBUG, INFO, WARNING, ERROR, CRITICAL) and JSON context
- **Utils.php** - CSRF helpers, email validation, time parsing (`parseTimeExpression`), encryption utilities

### Request Flow

1. Web pages (login.php, dashboard.php, etc.) authenticate via sessions
2. Background processing via `cron.php` runs EmailIngestor then EmailProcessor; records `last_cron_run` to `system_settings` on success
3. Action URLs (snooze/cancel) handled by `actions/exec.php`
4. API endpoints in `api/` for AJAX operations

### API Endpoints (api/)

- **update_theme.php** - POST, AJAX CSRF, saves user light/dark theme preference
- **create_reminder.php** - POST, AJAX CSRF, creates a reminder directly from the dashboard (no email needed)
- **reschedule_reminder.php** - POST, AJAX CSRF, updates the action timestamp of an existing reminder (ownership checked)
- **update_reminder.php** - POST, AJAX CSRF, moves a Kanban card between time columns (reschedules by column name)
- **update_category.php** - POST, AJAX CSRF, sets or clears the `catID` on a reminder card (ownership + category validity checked)
- **cancel_reminder.php** - POST, AJAX CSRF, sets `processed = -2` (CANCELLED) on a reminder; uses `Session::isLoggedIn()` directly so auth failure returns JSON 401 instead of a redirect
- **update_note.php** - POST, AJAX CSRF, saves or clears the `notes` field on a reminder (ownership checked)
- **auto_set_timezone.php** - POST, AJAX CSRF, sets `users.timezone` from the browser's `Intl.DateTimeFormat` API; only applies if the user's timezone is currently null or `UTC`

### AJAX endpoint pattern

All AJAX endpoints must:

1. Check `Session::isLoggedIn()` and return JSON `{'error': ...}` + HTTP 401 — **never** call `Session::requireAuth()` which redirects and leaves an empty body
2. Check `Utils::validateAjaxCsrf()` return value and return JSON + HTTP 403 on failure
3. Always output `Content-Type: application/json` as the first header

### Email Status States

- `processed = NULL/0` - Unprocessed, waiting for analysis
- `processed = 1` - Processed, waiting for action timestamp
- `processed = 2` - Reminded, action sent to user
- `processed = -2` - Cancelled

### Time Expression Parsing

`Utils::parseTimeExpression($expr, $baseTimestamp, $defaultHour = 17)` handles:

- `tomorrow`, `monday`, `8am` - Day/time aliases via `strtotime()`
- `2hours`, `1week` - Relative durations
- `31dec`, `dec31`, `15mar` - Digit+month without space (auto-spaced before strtotime)
- `next-tuesday` - Hyphenated day expressions (hyphens replaced with spaces)
- `eod` - End of day at `$defaultHour`; rolls to tomorrow if past
- `eow` - End of week (Friday) at `$defaultHour`; rolls to next Friday if past
- `check`, `search`, `upcoming`, `set` - Special commands (handled in EmailProcessor)

## Configuration

Environment variables in `.env`:

- `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` - MySQL connection
- `IMAP_SERVER`, `IMAP_USER`, `IMAP_PASS` - Email ingestion (IMAP mode)
- `MAILGUN_API_KEY`, `MAILGUN_DOMAIN` - Email ingestion (Mailgun mode)
- `APP_URL` - Base URL for web app (e.g., `https://app.snoozeme.app`)
- `MAIL_DOMAIN` - Domain for snoozer email addresses (e.g., `snoozeme.app`)
- `TRUSTED_PROXY` - (optional) IP of a trusted reverse proxy; when set, `X-Forwarded-For` etc. are only trusted from this address. If unset, proxy headers are trusted only when `REMOTE_ADDR` is a private/loopback address.

## Database Tables

- `users` - User accounts with bcrypt passwords, timezone, theme preferences, `DefaultReminderTime`
- `emails` - Reminder queue with message_id, timestamps, processing status, `notes` (TEXT, migration 008)
- `email_templates` - Customizable email templates (wrapper, reminder); reminder template uses `{{SUBJECT}}`, `{{NOTES_BLOCK}}`, `{{CANCEL_URL}}`, `{{SNOOZE_BUTTONS}}`
- `emailCategory` - Kanban board categories
- `login_attempts` - Rate limiting tracking for brute force protection
- `audit_logs` - Admin action audit trail (user changes, password resets, logins)
- `system_settings` - Key/value store; currently used for `last_cron_run` health tracking (migration 005)

## Migrations

Apply in order from project root:

| File                                          | Description                                              |
|-----------------------------------------------|----------------------------------------------------------|
| `Database_Export.sql`                         | Base schema                                              |
| `migrations/005_create_system_settings.sql`   | `system_settings` table for cron health                  |
| `migrations/006_add_thread_reminders.sql`     | `thread_reminders` column on `users`                     |
| `migrations/007_add_recurrence.sql`           | `recurrence` column on `emails`                          |
| `migrations/008_add_notes.sql`                | `notes` column on `emails`; updates reminder template    |

## Security

- **CSRF Protection** - All forms use `Utils::csrfField()` and validate with `Utils::validateCsrfToken()`. AJAX endpoints accept `X-CSRF-Token` header via `Utils::validateAjaxCsrf()`. All pages with AJAX must include `Utils::csrfMeta()` in `<head>`.
- **Session Management** - `Session::start()` handles secure cookie settings (HttpOnly, Secure, SameSite=Lax), 30-minute inactivity timeout, and session ID regeneration after login
- **Security Headers** - Automatically sent via `Session::sendSecurityHeaders()`: X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, CSP, Referrer-Policy, Permissions-Policy
- **Password Hashing** - bcrypt via `password_hash()` / `password_verify()`
- **SQL Injection** - Prepared statements throughout; all status constants bound as `?` parameters (never interpolated)
- **Email Header Injection** - `\r\n` stripped from `$to`, `$subject`, and `$inReplyToMessageId` in `Mailer` before building headers
- **Action URL Security** - AES-256-CBC encrypted message IDs with per-email SSL keys; IV generated with `random_bytes()`
- **Rate Limiting** - Login and password-reset attempts limited to 5 per 15 minutes per IP via RateLimiter class; proxy headers only trusted from private/loopback addresses or `TRUSTED_PROXY` env var
- **Audit Logging** - Admin actions logged via AuditLog class (user CRUD, password resets, template changes, logins)
- **Transaction Safety** - Email ingestion uses database transactions to prevent data loss
- **Password Reset** - Token-based flow only (`generatePasswordSetupToken` + `sendPasswordSetupEmail`); plaintext passwords are never generated or displayed
- **DB Error Disclosure** - Raw MySQLi errors are `error_log()`'d internally; users receive a generic message only
- **Cryptographic Randomness** - `random_bytes()` used for all security-sensitive generation (CSRF tokens, SSL keys, message IDs, AES IVs)

## Admin Pages

| Page                   | Description                                              |
|------------------------|----------------------------------------------------------|
| `users.php`            | User management (create, edit, delete, invite)           |
| `admin_templates.php`  | Email template editor                                    |
| `admin_audit.php`      | Audit log viewer with action/email filter and pagination |

All admin pages require `Session::requireAdmin()`.

## Key Technical Notes

- No PHP framework - vanilla PHP with custom class structure
- Frontend uses Bootstrap 4.2.1 with dark/light theme support
- Action URLs use `APP_URL` env variable (configurable per environment)
- Windows deployment via IIS (web.config present)
- Reminders created from the dashboard use `message_id = bin2hex(random_bytes(16)) . '@snoozer'` and `toaddress = 'web@{MAIL_DOMAIN}'`
- Snooze buttons in reminder emails INSERT a new reminder row (new message_id, new sslkey) rather than updating the original; the original stays as `REMINDED` (processed=2). Notes carry over to the new row. The `Release now` button still UPDATEs in place.
- Dashboard urgency badges use calendar date comparison (not a 86400s window) to correctly distinguish Today / Tomorrow / This week in the user's timezone

## Dashboard Views

`dashboard.php` has three tab views:

- **Upcoming** - Paginated list of pending reminders with note, release, reschedule, and cancel actions
- **History** - Reminded and cancelled reminders
- **Related** - All upcoming reminders grouped by shared keywords (words ≥4 chars, stop-words excluded). Groups of 2+ are shown with a purple border. Uses a greedy keyword-overlap clustering algorithm — O(n²) so suitable for typical reminder counts.

### Dashboard UX notes

- Cancel uses AJAX (`api/cancel_reminder.php`) with a fade-out animation and toast; no page reload
- Notes are saved inline via `api/update_note.php`; the pencil button turns blue when a note exists
- Search input has 350ms debounce — no Enter required
- On first load, if user timezone is null/UTC, browser timezone is auto-detected via `Intl.DateTimeFormat` and saved silently via `api/auto_set_timezone.php`; page reloads once to apply
- Inline JS arguments that could contain user data (subjects, notes) always use `data-*` attributes + `htmlspecialchars(..., ENT_QUOTES)` — never raw `json_encode` inside `onclick="..."` attributes

## Kanban Board

`kanban.php` displays upcoming reminders in three time-based columns — **Today**, **This Week**, **Upcoming** — using SortableJS for drag-to-reschedule.

### Category chips

Each card has a coloured category chip (`emailCategory` table: Delayed/Delegated/Doing/Dusted). Clicking the chip opens a floating picker to assign or remove a category. The selection is saved via `api/update_category.php`. Colours:

| ID | Name       | Colour    |
|----|------------|-----------|
| 1  | Delayed    | `#e67e22` |
| 2  | Delegated  | `#5dade2` |
| 3  | Doing      | `#2ecc71` |
| 4  | Dusted     | `#95a5a6` |

### Filter bar

Pills above the board: **All | Delayed | Delegated | Doing | Dusted | Untagged**. Filtering is client-side; column counts update automatically. After a category change the active filter re-applies immediately.

### Drag-to-reschedule

Dragging a card to a different column calls `api/update_reminder.php` with the column name (`today` / `week` / `later`) and reschedules the `actiontimestamp` accordingly.

---

## Recurring Reminders

Email `daily@domain`, `weekly@domain`, `monthly@domain`, or `weekdays@domain` to create a recurring reminder. After each reminder fires, `EmailProcessor` calculates the next occurrence and resets the row to `processed=1` with the new `actiontimestamp`. Cancelling via the action URL sets `processed=-2` and ends the series.

The `recurrence` column (migration 007) stores `null` for one-time reminders and the recurrence type string for recurring ones. Dashboard and Kanban show a `↻ daily` badge on recurring cards.

## Reply-to-Snooze

When a user replies to a reminder email and changes the To: address to a time expression (e.g. `tomorrow@domain`), EmailProcessor detects the `In-Reply-To`/`References` headers referencing an existing reminder owned by that user, reschedules it to the new time, and marks the reply as `IGNORED`. No schema change required — the raw header is already stored in `emails.header`.

## Notes on Reminders

Users can attach a text note to any reminder via the pencil icon on the dashboard. Notes are:

- Stored in `emails.notes` (TEXT, added by migration 008)
- Displayed as a purple-bordered block in the reminder email (between subject and snooze buttons)
- Carried over when a reminder is snoozed (new row inherits the original's note)
- Editable/clearable at any time via the dashboard modal
