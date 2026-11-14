# ExpTix

**Expert Tickets: open-source hotel operations and ticketing.**

ExpTix gives every department in a hotel one shared place to log, route and track work: guest requests, repairs, complaints, housekeeping, food safety checks, events, security incidents and shift notes. It runs in any browser, installs on a phone like an app, and is self-hosted on ordinary PHP hosting.

## Features

- **Tickets for every department:** front office, housekeeping, engineering, food and beverage, kitchen, banquets, security, IT, laundry, sales and administration. Free text auto-routes to the right team. SLAs with warnings, breaches and escalation. Checklists, labour, parts, photos, follow-up tasks for other teams, repeat-issue detection, guest-visible messages and service recovery.
- **Housekeeping board:** live room status, credit-based auto-assignment, DND, rush, declined service, out of order, inspections and minibar posting.
- **Engineering:** assets, preventive maintenance, parts and stock, vendors with insurance expiry, purchase requests with approval.
- **Kitchen and F&B:** HACCP temperature logging (out-of-range readings open an urgent ticket), 86 list, allergy alerts, outlets, events and BEOs that create department tasks.
- **Security:** incident reports, key control, lost and found.
- **Operations:** shift logbook with read receipts, announcements, staff schedule, calendar, reports with CSV export.
- **Guest QR portal:** one code per room and public area. No app or sign-in. Live tracking, messaging and a 1 to 5 rating; low ratings alert the front office.
- **Notifications:** in-app, web push, email, SMS (Twilio), Microsoft Teams, Slack and signed outbound webhooks.
- **Integrations:** Oracle Opera Cloud (OHIP), Canary, email to ticket (IMAP), IoT sensors, and a generic endpoint for Zapier, Make and Power Automate. Every integration has a mock mode for demos.
- **API and AI:** REST API with OpenAPI 3.1, and a built-in Model Context Protocol (MCP) server so AI assistants can read the queue, triage requests and update work under the same permissions as a staff user.
- **Security:** Argon2id passwords, lockout, TOTP MFA, session limits, scoped API tokens stored as hashes, a hash-chained audit log, encrypted integration secrets, role and department access, strict security headers. Controls are mapped to NIST SP 800-53 Rev. 5 and SP 800-63B on a built-in compliance page.

## Quick start

Needs PHP 8.1+ with `pdo_sqlite`, `sodium`, `openssl`, `mbstring` and `curl`.

```bash
git clone https://github.com/moosesystems/EXPTIX.git exptix
cd exptix
php app/bin/cli.php migrate
php app/bin/cli.php seed          # loads a fictional 182-room demo hotel and prints the passwords
php -S localhost:8080 -t public public/index.php
```

Open <http://localhost:8080> and sign in as `gm` with the `demo_password` that `seed` printed.

## Documentation

- **Deployment guide:** [Markdown](docs/DEPLOYMENT.md) · [HTML](docs/DEPLOYMENT.html) · [Word](docs/DEPLOYMENT.docx). Local trial, Linux servers (nginx or Apache), shared hosting, configuration, cron, notifications, integrations, hardening, backups and upgrades.
- **Staff how-to guide:** `public/HowTo.html`, served at `/HowTo.html` on every install. Explains each screen by role.
- **API reference:** served at `/docs` and `/api/openapi.json` on every install.

## Project layout

```
app/       PHP application, CLI and config (never web reachable)
public/    Web root: front controller, assets, how-to guide
deploy/    Example nginx, Apache and cron files
scripts/   Helper scripts
docs/      Deployment guide
```

## Status

ExpTix is at version 0.9: feature complete for a single property and suitable for pilots. Before production use with real guest data, work through the security checklist in the deployment guide. Multi-property support, single sign-on and translations are not built yet. Contributions are welcome.

## Demo data

Everything `seed` creates is fictional: the hotel, guests, staff, vendors, events and tickets. Any resemblance to real people or businesses is coincidental.

## Contributing and security

See [CONTRIBUTING.md](CONTRIBUTING.md). Please report vulnerabilities privately as described in [SECURITY.md](SECURITY.md), not in public issues.

## Licence

Copyright (C) 2026 The ExpTix contributors.

ExpTix is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version. It is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See [LICENSE](LICENSE).

If you run a modified version for users over a network, the AGPL requires you to offer them its source. Set `source_url` in your config to your repository.

Oracle, Opera, Canary, dormakaba, Twilio, Microsoft Teams, Slack and other names are trademarks of their owners. ExpTix is not affiliated with or endorsed by them.
