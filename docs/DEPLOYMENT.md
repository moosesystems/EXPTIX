# ExpTix deployment guide

ExpTix is a self-hosted operations and ticketing system for hotels. It is one PHP application with a browser front end, no build step and no framework. This guide covers every supported way to install it, from a five-minute local trial to a hardened production server.

ExpTix is free software under the GNU Affero General Public License v3. If you run a modified copy for other people over a network, you must offer them its source code. See [Licence obligations](#licence-obligations).

## Contents

1. [Choose a deployment](#choose-a-deployment)
2. [Requirements](#requirements)
3. [How the files are laid out](#how-the-files-are-laid-out)
4. [Quick start: local trial](#quick-start-local-trial)
5. [Production on a Linux server (nginx or Apache)](#production-on-a-linux-server)
6. [Shared hosting (cPanel, Plesk and similar)](#shared-hosting)
7. [Running in a sub-folder](#running-in-a-sub-folder)
8. [Configuration reference](#configuration-reference)
9. [Database: SQLite or MySQL](#database-sqlite-or-mysql)
10. [First sign-in and setting up the property](#first-sign-in-and-setting-up-the-property)
11. [Scheduled jobs (cron)](#scheduled-jobs-cron)
12. [Email, push, SMS and chat notifications](#notifications)
13. [Integrations and webhooks](#integrations-and-webhooks)
14. [API tokens and the MCP server](#api-tokens-and-the-mcp-server)
15. [Security hardening checklist](#security-hardening-checklist)
16. [Backups and restore](#backups-and-restore)
17. [Upgrading](#upgrading)
18. [Troubleshooting](#troubleshooting)
19. [Command line reference](#command-line-reference)
20. [Licence obligations](#licence-obligations)

## Choose a deployment

| You want to | Use | Database | Time |
|---|---|---|---|
| Try it on your own computer | [Quick start](#quick-start-local-trial) | SQLite | 5 minutes |
| Run it for one property on your own server | [Linux server](#production-on-a-linux-server) | MySQL 8 or MariaDB 10.6+ (SQLite works for small sites) | 1 hour |
| Run it on web hosting you already pay for | [Shared hosting](#shared-hosting) | MySQL from the hosting panel | 1 hour |

Whichever you pick, a production install needs HTTPS, the cron job, and `demo_mode` set to `false`.

## Requirements

**PHP 8.1 or later** (tested on 8.2) with these extensions:

| Extension | Why | Required |
|---|---|---|
| `pdo_sqlite` or `pdo_mysql` | Database | One of them |
| `sodium` | Encrypts integration secrets at rest | Yes |
| `openssl` | Web push (VAPID) signing | Yes |
| `mbstring` | Text handling | Yes |
| `curl` | Opera, Canary, Twilio, Teams, Slack and webhook calls | Yes |
| `json`, `fileinfo` | API and uploads | Yes (usually built in) |
| `imap` | Email to ticket | Only if you use email intake |

PHP must be built with Argon2 support (`PASSWORD_ARGON2ID`). Most distributions are. If it is not, ExpTix falls back to bcrypt.

**Web server:** nginx with PHP-FPM, or Apache 2.4 with `mod_rewrite` and `AllowOverride All`. LiteSpeed works with the included `.htaccess`.

**Database:** SQLite 3.35+ (bundled with PHP) or MySQL 8 / MariaDB 10.6+, `utf8mb4`.

**Other:** a TLS certificate (Let's Encrypt is fine), a cron scheduler, and a mail transfer agent if you want email (`sendmail` or `msmtp`).

**Browsers:** current Chrome, Edge, Safari and Firefox on desktop, Android and iOS. Web push on iPhone needs iOS 16.4+ and the app added to the Home Screen.

ExpTix loads one third-party script, the QR code library from `cdnjs.cloudflare.com`, and only on the Room QR codes page. Nothing else leaves the server unless you switch on an integration.

## How the files are laid out

```
exptix/
├── app/                  Application code. Never serve this folder.
│   ├── bin/cli.php       Command line: migrate, create-admin, seed, cron, backup, token
│   ├── config/
│   │   ├── config.php    Defaults (committed)
│   │   └── local.php     Your settings and secrets (you create it, never committed)
│   ├── data/             Created at runtime: database, keys, sessions, uploads, logs, backups
│   └── src/              PHP classes
├── public/               The only folder the web server serves
│   ├── index.php         Front controller
│   ├── .htaccess         Apache and LiteSpeed rewrite rules
│   ├── HowTo.html        Staff how-to guide
│   └── assets/           JavaScript, CSS, icons, service worker, manifest
├── deploy/               Example nginx, Apache and cron files
├── scripts/              Helper scripts
└── docs/                 This guide (Markdown, HTML and Word)
```

`public/index.php` finds the app folder at `../app` by default. If you split them (common on shared hosting), set the environment variable `EXPTIX_APP_ROOT` to the full path of `app/`, or edit the one `define('APP_ROOT', ...)` line in `public/index.php`.

`app/data/` holds everything that changes. The web server user must be able to write to it, and nobody else should be able to read it. It contains:

| Path | What it is | Lose it and |
|---|---|---|
| `data/exptix.sqlite` | Database (SQLite only) | You lose all data |
| `data/app.key` | Key that encrypts integration secrets | Stored integration secrets cannot be decrypted; re-enter them |
| `data/sessions/` | Sign-in sessions | Everyone signs in again |
| `data/uploads/` | Ticket photos and files | Attachments are gone |
| `data/logs/` | Error, cron and scheduler logs | Nothing important |
| `data/backups/` | Output of `cli.php backup` | Your local backups |
| `data/demo-credentials.txt` | Demo passwords written by `seed` | Run `seed --reset` again (demo only) |

## Quick start: local trial

This runs the full app with demo data on your own computer using PHP's built-in web server. Do not use the built-in server in production.

```bash
git clone https://github.com/moosesystems/EXPTIX.git exptix
cd exptix
php app/bin/cli.php migrate
php app/bin/cli.php seed
```

`seed` prints the demo passwords and saves them in `app/data/demo-credentials.txt`:

```json
{
    "admin_password": "…",
    "demo_password": "Demo-…",
    "sensor_token": "…",
    "rooms": 182,
    "tickets": 175,
    "users": 40
}
```

Start the server and open <http://localhost:8080>:

```bash
php -S localhost:8080 -t public public/index.php
```

Sign in as `admin` with `admin_password`, or as any demo user (`gm`, `fo.manager`, `hk.manager`, `chief.eng`, `chef`, `hk1` to `hk8`, `eng1` to `eng3`, `fd1` to `fd4`, `auditor`, `contractor` and others) with `demo_password`. The GM account sees every department and is the best place to start.

To start the demo over:

```bash
php app/bin/cli.php seed --reset
```

`seed --reset` drops every table and deletes all data. Never run it on a live system.

To see SLA timers and escalations move, run the scheduler in a second terminal:

```bash
while true; do php app/bin/cli.php cron; sleep 60; done
```

## Production on a Linux server

These steps use Ubuntu 24.04 LTS or Debian 12 with nginx, PHP-FPM 8.2 and MySQL 8. Adjust package names and the PHP version for your distribution.

### 1. Install packages

```bash
sudo apt update
sudo apt install nginx mysql-server php8.2-fpm php8.2-cli php8.2-mysql php8.2-sqlite3 \
  php8.2-mbstring php8.2-curl php8.2-xml php8.2-imap php8.2-intl git certbot python3-certbot-nginx
php -m | grep -E 'sodium|openssl|pdo_mysql|mbstring|curl'
```

### 2. Get the code

```bash
sudo mkdir -p /opt/exptix
sudo chown "$USER": /opt/exptix
git clone https://github.com/moosesystems/EXPTIX.git /opt/exptix
```

### 3. Create the database

```bash
sudo mysql <<'SQL'
CREATE DATABASE exptix CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER 'exptix'@'localhost' IDENTIFIED BY 'use-a-long-random-password';
GRANT ALL PRIVILEGES ON exptix.* TO 'exptix'@'localhost';
FLUSH PRIVILEGES;
SQL
```

Generate a password with `openssl rand -base64 24`.

### 4. Configure

Either run the helper, which asks for the database details and writes `app/config/local.php` with mode 600:

```bash
cd /opt/exptix
./scripts/configure-mysql.sh
```

or copy the example and edit it:

```bash
cp app/config/local.example.php app/config/local.php
chmod 600 app/config/local.php
nano app/config/local.php
```

At minimum set `property_name`, `property_code`, `base_url`, `timezone`, `locale`, `currency`, the `db` block and `demo_mode => false`. Every setting is described in the [configuration reference](#configuration-reference).

### 5. Set ownership

The web server and the cron job must run as the same user, so files they create stay readable by both.

```bash
mkdir -p /opt/exptix/app/data
sudo chown -R www-data:www-data /opt/exptix/app/data /opt/exptix/app/config/local.php
sudo chmod 750 /opt/exptix/app/data
```

### 6. Create the tables and the first administrator

```bash
cd /opt/exptix
sudo -u www-data php app/bin/cli.php migrate
sudo -u www-data php app/bin/cli.php create-admin admin "Your Name" you@example.com
```

`create-admin` prints a temporary password. You must change it at first sign-in.

For an evaluation server you can run `seed` instead to load the demo hotel. Do not load demo data into a system you will use for real guests.

### 7. Configure the web server

**nginx:** copy `deploy/nginx.conf` to `/etc/nginx/sites-available/exptix`, change `server_name`, the certificate paths and the PHP-FPM socket, then:

```bash
sudo ln -s /etc/nginx/sites-available/exptix /etc/nginx/sites-enabled/
sudo certbot certonly --nginx -d exptix.example.com
sudo nginx -t && sudo systemctl reload nginx
```

**Apache:** enable the modules, copy `deploy/apache-vhost.conf`, and edit it the same way:

```bash
sudo a2enmod rewrite headers ssl proxy_fcgi setenvif
sudo cp deploy/apache-vhost.conf /etc/apache2/sites-available/exptix.conf
sudo a2ensite exptix
sudo certbot certonly --apache -d exptix.example.com
sudo apachectl configtest && sudo systemctl reload apache2
```

Whichever server you use, only `public/` is the document root. The nginx file passes the `Authorization` header to PHP, which API tokens and MCP need. The `.htaccess` does the same on Apache.

### 8. Install the cron job

```bash
sudo crontab -u www-data -e
```

Paste the lines from `deploy/crontab.example`, with your path. See [Scheduled jobs](#scheduled-jobs-cron).

### 9. Check it

```bash
curl -s https://exptix.example.com/api/health
# {"ok":true,"app":"ExpTix","version":"…","db":"mysql","time":"…"}
```

Open the site, sign in as your administrator, set a new password, turn on MFA under **My profile**, then follow [First sign-in](#first-sign-in-and-setting-up-the-property).

## Shared hosting

Most hosting plans with PHP 8.1+, MySQL, SSH or a file manager, and cron will run ExpTix. The approach is to put `app/` outside the public web folder and the contents of `public/` inside it.

1. **Create a MySQL database and user** in the hosting panel. Note the host name, which is often not `localhost`.
2. **Upload the code.** With SSH, `git clone` into your home folder, for example `~/exptix`. Without SSH, upload the repository as a zip and extract it there.
3. **Put the app outside the web root.** If your site's web folder is `~/public_html`, the simplest layout is:

    ```
    ~/exptix/app/            application (not web reachable)
    ~/public_html/           contents of exptix/public/ copied here
    ```

    Copy `public/` across, including the hidden `.htaccess`:

    ```bash
    cp -a ~/exptix/public/. ~/public_html/
    ```

4. **Tell `index.php` where the app is.** Add this line to the top of `~/public_html/.htaccess`, with your real path:

    ```apache
    SetEnv EXPTIX_APP_ROOT /home/youruser/exptix/app
    ```

    Some hosts do not pass `SetEnv` through to PHP. If the site shows an error about `config.php`, edit `~/public_html/index.php` instead and change the `define('APP_ROOT', ...)` line to `define('APP_ROOT', '/home/youruser/exptix/app');`.

5. **Configure.** Run `./scripts/configure-mysql.sh` from `~/exptix` over SSH, or create `app/config/local.php` from `local.example.php` in the file manager. Set its permissions to 600.
6. **Create the tables and administrator** over SSH:

    ```bash
    cd ~/exptix
    php app/bin/cli.php migrate
    php app/bin/cli.php create-admin admin "Your Name" you@example.com
    ```

    If the host has no SSH, temporarily create a PHP file that runs these, open it once in the browser, then delete it immediately. SSH is strongly preferred.

7. **Add the cron job** in the hosting panel: `php /home/youruser/exptix/app/bin/cli.php cron` every minute. If the panel only allows every 5 minutes, SLA timers will still work, just less precisely. Use the full path to PHP 8 if the panel's default `php` is older (for example `/usr/local/php82/bin/php`).
8. **Turn on HTTPS** in the panel (most offer free Let's Encrypt certificates).

On shared hosting, `cli.php backup` needs `mysqldump` on the path. If it is not there, use the panel's own database backups.

## Running in a sub-folder

ExpTix can live at `https://example.com/exptix/` instead of a domain root. Change three things:

1. In `local.php`: `'base_path' => '/exptix'` and `'base_url' => 'https://example.com/exptix'`.
2. In `public/.htaccess` (Apache): `RewriteBase /exptix/` and `Header set Service-Worker-Allowed "/exptix/"`.
3. On nginx, wrap the locations in `location /exptix/ { ... }` with `alias`, or serve the sub-folder from its own `server` block, and set the `Service-Worker-Allowed` header to `/exptix/`.

The web app manifest uses relative paths and needs no change.

## Configuration reference

`app/config/config.php` holds the defaults. Put your changes in `app/config/local.php`, which returns an array merged over the defaults. Never edit `config.php` for site settings, so upgrades do not overwrite them.

| Setting | Default | What it does |
|---|---|---|
| `app_name` | `ExpTix` | Product name in the title bar and emails |
| `property_name` | `Demo Hotel` | Your property's name, shown to staff and guests |
| `property_code` | `DEMO` | Prefix for ticket numbers (`DEMO-000123`) |
| `operator` | empty | Optional "Operated by ..." text on the sign-in page |
| `base_path` | empty | URL path ExpTix lives under; empty at a domain root |
| `base_url` | `https://exptix.example.com` | Full public URL; used in emails, QR codes and the API docs |
| `timezone` | `UTC` | Property time zone (any PHP / IANA zone name). All data is stored in UTC |
| `locale` | `en-US` | Date and number formatting in the browser |
| `currency` | `USD` | ISO 4217 code for charges, recovery values and purchase requests |
| `source_url` | this repository | Link to the source code shown on the sign-in page (AGPL section 13) |
| `demo_mode` | `true` | Demo banner, email off, unsigned webhooks accepted, daily fake arrivals. **Set `false` in production** |
| `db.*` | SQLite in `app/data` | See [Database](#database-sqlite-or-mysql) |
| `security.session_idle_minutes` | `30` | Sign out after this much inactivity |
| `security.session_max_hours` | `12` | Sign out after this long regardless |
| `security.password_min_length` | `12` | Minimum password length (common passwords are also blocked) |
| `security.lockout_threshold` | `5` | Failed sign-ins before a lockout |
| `security.lockout_minutes` | `15` | Lockout length |
| `security.mfa_required_ranks_at_or_above` | `999` (off) | Force TOTP MFA from this role rank up. `70` covers managers and above |
| `security.audit_retention_days` | `2555` | How long audit log entries are kept (7 years) |
| `security.trusted_proxies` | empty | IP addresses of reverse proxies whose `X-Forwarded-For` is trusted |
| `uploads.path` | `app/data/uploads` | Where attachments are stored |
| `uploads.max_mb` | `12` | Largest upload; keep the web server's body limit above this |
| `uploads.types` | images, PDF, text, CSV | Allowed MIME types |
| `mail.from`, `mail.from_name` | `exptix@example.com`, `ExpTix` | Sender for notification email |
| `push.subject` | `mailto:admin@example.com` | Contact address sent to browser push services (VAPID) |

Role ranks, for `mfa_required_ranks_at_or_above`:

| Role | Rank |
|---|---|
| Administrator | 100 |
| General Manager | 90 |
| Department Manager | 70 |
| Supervisor | 50 |
| Front Desk / Guest Services | 40 |
| Staff | 30 |
| Contractor | 20 |
| Auditor (read-only) | 15 |

## Database: SQLite or MySQL

**SQLite** needs no setup and is the default. The database is `app/data/exptix.sqlite`, in WAL mode. It is fine for a trial and for a small property with a few dozen staff. Back it up with `cli.php backup`, which takes a consistent copy while the app runs.

**MySQL 8 or MariaDB 10.6+** is recommended for production: better concurrency, and standard tooling for backups and replication.

```php
'db' => [
    'driver' => 'mysql',
    'host'   => 'localhost',
    'port'   => 3306,
    'name'   => 'exptix',
    'user'   => 'exptix',
    'pass'   => '…',
],
```

`cli.php migrate` creates every table and adds any columns a new version needs. It never drops or renames anything, so it is safe to run on every upgrade.

To move from SQLite to MySQL, export each table to CSV from SQLite and import it into a freshly migrated MySQL database. There is no built-in converter yet.

## First sign-in and setting up the property

Sign in as the administrator, change the temporary password, then turn on MFA under **My profile** (any TOTP authenticator app works). Then work through **Admin** in this order:

1. **Departments:** the teams that receive work (front office, housekeeping, engineering and so on), with a manager and an email address for each.
2. **Users:** everyone who signs in, with role and department. Staff can belong to more than one department.
3. **Locations:** buildings, floors, outlets, meeting rooms, back-of-house areas. Every location and room gets a guest QR code; print the ones for public areas where guests can make requests.
4. **Room types** (with housekeeping credits and cleaning minutes) and **Rooms**.
5. **SLA policies:** response and resolve targets and who to escalate to.
6. **Request categories:** what people ask for, which department gets it, its default priority and SLA, and the keywords that auto-route free text. Mark the ones guests may choose on the QR portal.
7. **Checklist templates:** room inspections, opening and closing checks, fire and life safety rounds.
8. **Assets, PM schedules, parts and vendors** for engineering.
9. **Outlets and temperature points** for food safety logging.
10. **Notification rules:** who hears about what, and how.
11. **Room QR codes:** print one card per room and public area.
12. **Integrations:** Opera Cloud, Canary, email, SMS and so on.

The how-to guide at `/HowTo.html` explains each screen for staff, by role. Link it from your intranet or staff onboarding.

If you loaded the demo data to explore, start the real system on a fresh database rather than deleting demo records by hand.

## Scheduled jobs (cron)

One command does all scheduled work. Run it every minute as the web server user:

```cron
* * * * * php /opt/exptix/app/bin/cli.php cron >/dev/null 2>&1
```

Each run does only what is due. A lock file stops two runs overlapping.

| Job | How often | What it does |
|---|---|---|
| SLA sweep | 1 min | Warnings, breaches and escalations |
| Notifications | 1 min | Sends queued email, push, SMS, Teams, Slack and webhook messages |
| Preventive maintenance | 30 min | Opens PM tickets as they come due |
| Opera sync | `sync_minutes` (default 10) | Pulls room and reservation status |
| Email intake | 2 min | Turns new mailbox messages into tickets |
| Hygiene | 60 min | Auto-closes resolved tickets after 72 hours, returns out-of-order rooms whose end date has passed, flags overdue keys and temperature checks, expires 86-list items, and once a day checks low stock and vendor insurance and workers' compensation expiry |

Also schedule the daily backup and a weekly audit check. Both are in `deploy/crontab.example`.

If cron has not run for two minutes, ExpTix runs the scheduler during the next web request as a fallback. Do not rely on that: quiet overnight periods have no web requests, so escalations would stall.

## Notifications

Each user picks their channels under **My profile**. **Admin, Notification rules** sends events to people, roles, departments or addresses.

**In-app:** always on.

**Web push:** works with no setup. ExpTix creates its VAPID key pair on first use and stores it in the database. Users turn on push notifications in **My profile**. Set `push.subject` to a real contact address. On iPhone, users must first add ExpTix to the Home Screen.

**Email:** uses PHP `mail()`, so the server needs a working `sendmail`. To send through an SMTP relay or Microsoft 365, install `msmtp` and point PHP's `sendmail_path` at it. In `demo_mode`, email is skipped unless the **Email notifications** integration is switched on. Set SPF and DKIM for your `mail.from` domain, or messages will land in junk.

**SMS:** add Twilio credentials under **Admin, Integrations, Twilio SMS**.

**Microsoft Teams and Slack:** create an incoming webhook (Teams Workflows or a Slack app) and use its URL as the target of a notification rule with the Teams or Slack channel.

**Outbound webhooks:** under **Admin, Webhooks**, send events to any HTTPS endpoint. Each POST carries `X-ExpTix-Event`, `X-ExpTix-Timestamp` and `X-ExpTix-Signature: sha256=HMAC(secret, timestamp + "." + body)`. Verify the signature and reject old timestamps.

## Integrations and webhooks

Configure integrations under **Admin, Integrations**. Each one has a **mock** mode that behaves realistically without credentials, and a **live** mode. Secrets are encrypted with the key in `app/data/app.key`.

| Integration | Live mode needs | Inbound URL |
|---|---|---|
| Oracle Opera Cloud (OHIP) | OHIP gateway URL, application key, client ID and secret, enterprise ID, hotel ID; a business events subscription | `/hooks/opera` |
| Canary Technologies | API base URL, API key, property ID, webhook signing secret | `/hooks/canary` |
| Email to ticket | IMAP host, mailbox, password or app password | (polled by cron) |
| Twilio SMS | Account SID, auth token, sending number | none |
| IoT sensors | Shared token, sent as `X-Sensor-Token` | `/hooks/sensor` |
| Zapier, Make, Power Automate | An API token with `write` scope | `/hooks/inbound` |
| dormakaba, BMS | Placeholders; need vendor API access | `/hooks/sensor` (BMS alarms) |

**Opera Cloud:** register an application in the OHIP developer portal for the property, copy the values above into the integration, and subscribe business events (check-in, check-out, room move, room status) to `https://your-host/hooks/opera` with the events secret. The API paths ExpTix calls are configurable. Check them against your OHIP catalogue during onboarding.

**Webhook signatures:** inbound Opera and Canary events must carry an HMAC-SHA256 of the raw body in `X-Signature`, `X-Canary-Signature` or `X-ExpTix-Signature`, or the shared secret in `X-Webhook-Token`. With `demo_mode` on and no secret set, unsigned events are accepted. With `demo_mode` off, they are rejected.

Full request and response formats are in the live API documentation at `/docs` and `/api/openapi.json`.

## API tokens and the MCP server

ExpTix has a REST API (`/api/...`, OpenAPI 3.1 at `/api/openapi.json`, readable docs at `/docs`) and a Model Context Protocol server at `/mcp` for AI assistants.

Create tokens under **Admin, Integrations, API tokens**, or from the command line:

```bash
php app/bin/cli.php token gm "Assistant" read,write,mcp
```

Tokens start with `xtx_`, are shown once, and are stored only as hashes. Scopes are `read`, `write`, `mcp` and `admin`. A token acts with the role of the user it belongs to, so give assistants a dedicated user with the least role that does the job.

```bash
curl -H "Authorization: Bearer xtx_…" https://exptix.example.com/api/tickets?status=open
```

To connect an MCP client, point it at `https://exptix.example.com/mcp` (Streamable HTTP, JSON responses) with the header `Authorization: Bearer xtx_…`.

## Security hardening checklist

ExpTix ships with controls mapped to NIST SP 800-53 Rev. 5 and SP 800-63B. The **Security and compliance** page under Admin shows their live status. Before you go live:

- [ ] `demo_mode` is `false`.
- [ ] The site is HTTPS only, with HTTP redirecting to HTTPS. ExpTix sends HSTS.
- [ ] Only `public/` is web reachable. Check that `https://your-host/../app/config/config.php` and `https://your-host/app/data/` return 404 or 403.
- [ ] `app/config/local.php` is mode 600 and `app/data/` is mode 750, owned by the web server user.
- [ ] The first administrator has changed the temporary password and turned on MFA.
- [ ] `security.mfa_required_ranks_at_or_above` is `70` or lower.
- [ ] Every integration with an inbound URL has its webhook secret set.
- [ ] `security.trusted_proxies` lists your reverse proxy, if you have one, so lockouts and logs see real client addresses.
- [ ] Backups run daily, are copied off the server, and you have tested a restore.
- [ ] `app/data/app.key` is backed up separately from the database.
- [ ] The audit webhook forwards to your SIEM or log platform, or you review the audit log regularly.
- [ ] The server's PHP, web server and database get security updates.
- [ ] Guest data retention and a privacy notice for the guest QR portal are agreed with the property, and hosting meets your privacy law's data residency rules.
- [ ] You have had a penetration test, or at least an authenticated vulnerability scan, before real guest data goes in.

Report vulnerabilities as described in `SECURITY.md`.

## Backups and restore

```bash
php app/bin/cli.php backup
```

writes `app/data/backups/exptix-YYYYMMDD-HHMMSS.sqlite` (SQLite) or `.sql.gz` (MySQL, through `mysqldump --single-transaction`) and deletes local backups older than 14 days. Schedule it daily and copy the folder off the server, for example with `restic`, `rclone` or `borg`.

Back up these too. They are not in the database dump:

- `app/data/app.key` (without it, stored integration secrets are unreadable)
- `app/data/uploads/`
- `app/config/local.php`

**Restore, MySQL:**

```bash
gunzip -c exptix-20260101-031500.sql.gz | mysql -u exptix -p exptix
```

**Restore, SQLite:** stop PHP-FPM (or put the site in maintenance), copy the backup over `app/data/exptix.sqlite`, delete any `exptix.sqlite-wal` and `exptix.sqlite-shm` files, then start again.

After any restore, put `app.key` and `uploads/` back, then run `php app/bin/cli.php verify-audit` to confirm the audit chain is intact.

## Upgrading

```bash
cd /opt/exptix
php app/bin/cli.php backup
git pull
sudo -u www-data php app/bin/cli.php migrate
sudo systemctl reload php8.2-fpm
```

Read `CHANGELOG.md` before upgrading. Browsers pick up new JavaScript and CSS on the next page load. The service worker refreshes on its own.

On shared hosting, also copy the new `public/` files into your web folder, keeping your edited `.htaccess` and `index.php`.

## Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| Blank page or "Something went wrong (reference ...)" | PHP error | Look up the reference in `app/data/logs/` |
| Error mentioning `config.php` | `index.php` cannot find the app folder | Set `EXPTIX_APP_ROOT` or edit the `APP_ROOT` line |
| Every page except the home page is 404 | Rewrites not working | Apache: enable `mod_rewrite` and `AllowOverride All`. nginx: check `try_files` |
| API tokens and MCP return 401 | `Authorization` header not reaching PHP | Use the included `.htaccess` or nginx `fastcgi_param HTTP_AUTHORIZATION` |
| Styles or scripts missing in a sub-folder | `base_path` wrong | See [Running in a sub-folder](#running-in-a-sub-folder) |
| SLA timers stall overnight, notifications late | Cron not running | Run `php app/bin/cli.php cron` by hand, check for errors in `app/data/logs/`, and fix the crontab |
| Push notifications never arrive | Service worker scope, HTTPS or iOS | HTTPS is required; check `Service-Worker-Allowed`; on iPhone add to Home Screen first |
| No email | No working `sendmail`, or `demo_mode` on | Test with `php -r "var_dump(mail('you@example.com','t','t'));"`; set `demo_mode` false |
| "Database already has users" on seed | `seed` refuses to touch a used database | That is deliberate. Use `create-admin` for production |
| Account locked | Too many failed sign-ins | Wait `lockout_minutes` (15 by default) and try again |
| Integration secrets show as errors after a move | `app.key` not copied | Copy `app/data/app.key` from the old server, or re-enter the secrets |

## Command line reference

Run from the repository root as the web server user.

| Command | What it does |
|---|---|
| `php app/bin/cli.php migrate` | Create tables and add new columns. Safe to repeat |
| `php app/bin/cli.php create-admin [username] ["Full name"] [email]` | Create an administrator with a temporary password |
| `php app/bin/cli.php seed` | Load the demo hotel into an empty database |
| `php app/bin/cli.php seed --reset` | **Delete everything** and reload the demo hotel |
| `php app/bin/cli.php cron` | Run scheduled jobs that are due |
| `php app/bin/cli.php backup` | Back up the database to `app/data/backups` |
| `php app/bin/cli.php verify-audit` | Check the hash chain of the audit log |
| `php app/bin/cli.php token <username> <name> <scopes>` | Create an API token, e.g. `read,write,mcp` |

## Licence obligations

ExpTix is licensed under the GNU Affero General Public License, version 3 or (at your option) any later version. In plain terms:

- You may use, study, change and share it, including commercially.
- If you change it and let people use your changed version over a network (staff, guests or anyone else), you must offer those users the complete source code of your version under the same licence. ExpTix shows a **Source code** link on the sign-in page built from `source_url`. Point it at your own repository if you run a modified copy.
- Keep the copyright and licence notices.
- There is no warranty.

The full text is in `LICENSE`. Oracle, Opera, Canary, dormakaba, Twilio, Microsoft Teams, Slack and other product names are trademarks of their owners. ExpTix is not affiliated with or endorsed by any of them.
