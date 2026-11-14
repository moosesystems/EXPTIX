# Changelog

## Unreleased

- Security: new control SI-2 on the Security and compliance page and `cli.php check-php` warn when the server's PHP lacks the fixes for the PHP2Shell remote code execution flaws (CVE-2026-109618, CVE-2026-109619). Minimum documented PHP is now 8.2 on a patched release (8.2.35, 8.3.36, 8.4.27, 8.5.12 or newer). New "Keep PHP patched" section in the deployment guide.
- Fix: the PHP built-in server now serves CSS, JavaScript and images, so the quick start works.
- Demo mode banner reads "Demo mode · fictional hotel, guests and staff".
- Project website (GitHub Pages) with screenshots, and a new README.
- How-to-use library in docs/guide: 17 one-page sheets by role, 4 playbooks by property size, a rollout plan, 4 FAQs and a glossary, as web pages, PDF and Word. First-day and room attendant sheets in French, Spanish and Tagalog (machine-assisted, need native review).
- Website: role cards open a panel with that role's real screens, what they do, why, how and what they are notified about.
- Staff how-to guide: corrected who saves SLA policies and notification rules, who can unlock accounts, and some button names.

## 0.9.0 (2026-11-14)

First public release.

- Tickets, SLAs and escalation for every hotel department, with auto-routing, checklists, labour, parts and photos.
- Housekeeping board with credit-based auto-assignment and inspections.
- Engineering assets, preventive maintenance, stock, vendors and purchase requests.
- HACCP temperature logging, 86 list, allergies, outlets, events and BEOs.
- Security incidents, key control, lost and found.
- Logbook, announcements, schedule, calendar and reports.
- Guest QR portal with tracking, messaging and ratings.
- In-app, web push, email, SMS, Teams, Slack and webhook notifications.
- Opera Cloud (OHIP), Canary, IMAP, sensor and generic inbound integrations with mock modes.
- REST API (OpenAPI 3.1) and MCP server.
- `create-admin` command for production installs without demo data.
- Configurable time zone, locale, currency and base path.
