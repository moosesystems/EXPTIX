---
title: Large hotel or resort
subtitle: 300 to 1,000+ rooms, phased by department
audience: General managers, directors of operations, department heads and IT at large hotels and resorts
kind: playbook
group: size
order: 30
lang: en
---

## Who this is for

You run 300 to 1,000 or more rooms: a convention hotel, a 500-room resort with several pools and outlets, or a property spread over several buildings. You have many departments, three shifts, outside contractors, a banquet operation and people who want the numbers in their own BI tool.

## Be honest about scale first

ExpTix has been tested with a 182-room demo hotel. It has not been proven at 500 or 1,000 rooms. That does not mean it will not work. It means you must prove it at your property before you depend on it:

1. **Pilot first.** Start with one or two departments, not the whole hotel.
2. **Use MySQL on a proper server.** Not SQLite, not a low-end shared plan.
3. **Load-test before go-live.** Load your real room count, users and a realistic volume of jobs into a test install, then have many people use it at once. Watch page times and the cron run.
4. **Plan your own support.** ExpTix is free software with no vendor support contract. Support comes from your own IT, an IT provider you choose, or the community.

## What to switch on and what to leave off

| Area | Recommendation |
|---|---|
| Departments | All you need, renamed to match your org chart. Add departments (for example Spa, Recreation, Grounds) rather than overloading one. |
| Locations | Build the tree properly: buildings, floors, outlets, meeting rooms, back of house, mechanical rooms. Routing and reports depend on it. |
| Housekeeping board | On, with sections (**Section** on each room) and credits on every room type. |
| Engineering | Assets with criticality, PM, parts and stock, vendors, purchase requests with approval. |
| Banquets | **Events and BEOs** on. Each BEO creates tasks for setup, kitchen, AV and housekeeping. |
| Contractors | Contractor role for outside trades. They see only their own jobs. |
| Auditors | Auditor role for owners' reps, brand, insurers. Read-only. |
| API and BI | On, with a dedicated read-only user for the BI feed. |
| Opera and Canary | **Beta**, not tested live. Use **Mock** mode until a controlled live test passes. |
| Notifications | On, but designed (see below). |

## Hosting

- MySQL 8 or MariaDB 10.6+ on a server sized and monitored by IT, with daily backups copied off the server and a tested restore.
- nginx or Apache with PHP-FPM, HTTPS only, cron every minute.
- If you sit behind a reverse proxy or load balancer, set `security.trusted_proxies` so lockouts and logs see real addresses.
- Forward the audit webhook to your SIEM or log platform.
- Force MFA for managers and above (`mfa_required_ranks_at_or_above` at `70`).
- Have a penetration test, or at least an authenticated vulnerability scan, before real guest data goes in.

Keep a separate test install. Every upgrade, new checklist set or integration goes there first.

## Phased rollout by department

Do not go live everywhere on one day. A suggested order:

| Phase | Departments | Why this order |
|---|---|---|
| 1 | Engineering | Clear work orders, measurable, few guest-facing risks. Builds asset and PM history early. |
| 2 | Housekeeping and front office together | The board only works when both sides use it. |
| 3 | Guest QR portal, one tower or floor range first | Proves the guest flow before every room has a code. |
| 4 | Kitchen and F&B: food safety, 86 list, allergy alerts | Replaces paper temperature logs. |
| 5 | Banquets and events, Security, Laundry, IT | Brings the rest onto one system. |

Give each phase 2 to 4 weeks. Do not start the next phase until the current one runs without paper. The [rollout plan](playbook-rollout.html) covers training and go-live checks.

## Notification rules: stop alert fatigue

At this size, a careless rule sends hundreds of pushes a day and people stop looking. Design rules before go-live.

1. **Scope every rule to a department.** Use **Only for department** on each one.
2. **Use minimum priority.** Managers do not need a push for every Normal job. Set **Minimum priority** to High or Urgent for manager alerts.
3. **Send the right event to the right people.** **Ticket created** goes to the department on shift. **SLA at risk** goes to supervisors. **SLA breached** goes to department supervisors and managers.
4. **Pick the channel by urgency.** In-app and push for staff. Teams or Slack for a department channel. SMS only for Critical and life safety.
5. **Keep On shift honest.** New jobs go to people on shift. Staff who leave it ticked get alerts at home and learn to mute them.
6. **Review monthly.** Ask each manager: which alerts do you ignore? Fix or remove that rule.

## Who does what

| Person | Role |
|---|---|
| Internal ExpTix administrator (see below) | Administrator |
| GM, hotel manager, director of operations | General manager |
| Directors and department heads | Department manager |
| Shift leads, inspectors, sous chefs, security leads | Supervisor |
| Front desk, PBX, guest services, night audit | Front desk / guest services |
| Frontline teams | Staff |
| Elevator, HVAC, fire systems, pest control and other trades | Contractor |
| Owners' reps, asset managers, brand, insurers | Auditor |
| BI feed, AI assistant | A dedicated user with the least role that does the job, plus a scoped token |

### Staff an internal administrator

Name one person as the ExpTix owner, with a backup. At this size it is part of a real job, not a favour. They:

- add and deactivate **Users** (untick **Active**, never delete),
- keep categories, keywords and SLAs tidy with the department heads,
- own **Notification rules**, **Integrations** and **API and MCP** tokens,
- run **Verify integrity** on the **Audit log** and check backups,
- test upgrades on the test install, then apply them,
- collect feedback and decide what changes, and when.

### Contractors and auditors

Give each contracting company its own users with the Contractor role. Put insurance and workers' compensation expiry dates on each vendor so ExpTix warns you before paperwork runs out. Auditors get the Auditor role: they see jobs, food safety logs, incidents, reports, the audit log and the security page, and cannot change anything.

### API and BI

Use the REST API (OpenAPI 3.1 at `/api/openapi.json`, readable docs at `/docs`) for your BI tool. Create a dedicated user with the Auditor role (it sees every department and cannot change anything), give its token only the `read` scope, and set an expiry. The API pages through tickets 500 at a time with `limit` and `offset`. The CSV export on **Reports** is fine for a quick look but is not a data feed.

## A day in the life

- **Overnight:** PM jobs open on schedule. A freezer sensor posts an out-of-range reading and opens an Urgent job. Security logs a fire alarm in **Incident reports**.
- **07:00** Department heads read the **Shift logbook** and the Home tiles for overdue and breached jobs.
- **08:00** Housekeeping supervisors run **Auto-assign** by section.
- **10:00** The banquet team checks today's BEO tasks: who is ready, who is not.
- **Afternoon:** contractors work their jobs and log time and parts. The chief engineer reviews costly machines.
- **Evening:** the GM reviews guest complaints and service recovery from the day.

## Reports to check weekly

- Response and resolve times, and SLA misses, by department and priority.
- Guest ratings, complaints and service recovery value.
- Repeat-problem rooms and costly machines.
- PM on time, especially life safety.
- Inspection scores and food safety readings out of range.
- Your BI dashboards built from the API, if you have them.

Take a four-week baseline per department before setting targets.

## Common pitfalls

- **Going live everywhere at once.** One problem then hits every team at the same moment.
- **Skipping the load test.** Find the limits in a test window, not on a sold-out Saturday.
- **Alert storms.** See the notification rules above.
- **No owner.** An ExpTix with nobody in charge drifts: stale categories, old users, rules nobody understands.
- **A thin location tree.** "Somewhere in the east wing" cannot be routed or reported on.
- **Expecting the app in other languages.** The screens are English only today. Translate printed guides for your teams now.
- **Waiting for single sign-on.** It is on the roadmap (Entra ID, Google), not built. Plan on ExpTix passwords with MFA.

## When to grow

If you run more than one property, read the [group and chain playbook](size-group-chain.html). ExpTix is one property per install today.
