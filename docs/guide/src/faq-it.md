---
title: IT questions
subtitle: Short answers for the people who run it
audience: Hotel IT and IT providers
kind: faq
group: faq
order: 40
lang: en
---

## Where can we host it?

Any ordinary PHP 8.1+ host: your own Linux server (nginx or Apache with PHP-FPM) or shared hosting with PHP, MySQL and cron. HTTPS is required. Requirements and example configs are in the deployment guide.

## SQLite or MySQL?

SQLite for a trial or a small property with a few dozen staff. MySQL 8 or MariaDB 10.6+ for production. There is no built-in converter yet: moving from SQLite to MySQL means exporting tables to CSV and importing them.

## How do backups work?

Run `php app/bin/cli.php backup` daily from cron and copy `app/data/backups/` off the server. Back up `app/data/app.key`, `app/data/uploads/` and `app/config/local.php` separately: they are not in the database dump. After a restore, run `verify-audit`.

## How do we update?

Read `CHANGELOG.md`, back up, pull the new code, run `php app/bin/cli.php migrate`, reload PHP. Migrate never drops or renames anything, so it is safe to repeat.

## Does it need cron?

Yes, every minute. Cron runs SLA warnings and escalations, sends notifications, opens PM jobs and syncs integrations. Without it, timers stall overnight.

## Does it support single sign-on?

Not yet. Entra ID and Google are on the roadmap. Accounts live in ExpTix, with optional or forced TOTP two-step sign-in.

## How do we set up email?

ExpTix sends through PHP `mail()`, so the server needs a working `sendmail`. For an SMTP relay or Microsoft 365, use `msmtp`. Set SPF and DKIM for the sending domain, and make sure `demo_mode` is off.

## How do we set up SMS?

Add Twilio credentials under **Integrations**, Twilio SMS. Users add a mobile number and tick SMS alerts.

## How do push notifications work?

Web push works with no setup: ExpTix makes its own VAPID keys. Users turn it on in **My account**. On iPhone, iOS 16.4+ and adding ExpTix to the Home Screen are required. Pushes carry no payload, so no guest data passes through the push service.

## Teams and Slack?

Create an incoming webhook in Teams or Slack, then use its URL as the target of a notification rule.

## How do API tokens and MCP work?

Create a token in **API and MCP**, or with `cli.php token`. Scopes are read, write, mcp and admin; the token acts with its user's role, so give assistants a dedicated user with the least role needed. The REST API is described at `/docs` and `/api/openapi.json`; the MCP server is at `/mcp`.

## Where are the logs?

PHP errors, cron and scheduler logs are in `app/data/logs/`. An error page shows a reference you can look up there. User actions are in the **Audit log**, and an audit webhook can forward them to your SIEM.

## Are the integrations production ready?

Opera Cloud and Canary are beta: built to the vendors' documented patterns and not yet tested against live systems. Every integration, including email to ticket, sensors, SMS and webhooks, has a mock mode. Start in mock mode and test each link in your own environment before staff rely on it.

## Is there a Docker image?

Not yet. It is on the roadmap.

## How do we harden it?

Work through the security checklist in the deployment guide: `demo_mode` off, HTTPS only, only `public/` web reachable, strict file permissions, MFA for managers and up, webhook secrets set, tested backups and a vulnerability scan before real guest data.
