---
title: Mid-size hotel
subtitle: 50 to 250 rooms, department heads, the housekeeping board at the centre
audience: General managers, department managers and IT for full-service and select-service hotels
kind: playbook
group: size
order: 20
lang: en
---

## Who this is for

You run 50 to 250 rooms. Think of a 120-room downtown hotel with a front office, housekeeping, engineering, a restaurant and a few meeting rooms. Each department has a manager, shifts have supervisors, and work moves between teams all day.

This is the size ExpTix was built and tested for. The demo hotel has 182 rooms.

## What to switch on and what to leave off

| Area | Recommendation |
|---|---|
| Departments | Keep the ones you have. Rename to match your org chart, untick **Active** on the rest. |
| Housekeeping board | On. This is the centre of the day for front office and housekeeping. |
| Auto-assign and credits | On. Set credits per room type so suites count for more. |
| Inspections | On, with a room inspection checklist. Every failed line becomes a job. |
| Engineering | Assets for anything that costs money to fix, PM schedules, parts and stock. |
| Food safety | On if you run a kitchen. Add every fridge, freezer and hot-holding point. |
| Guest QR portal | On. One code in every room plus the lobby, pool and gym. |
| Events and BEOs | On if you sell meetings or banquets. |
| Security | Incident reports, **Key control** and **Lost and found**. |
| PMS | Optional. Run it manually, or try the beta Opera Cloud link (see below). |
| Notifications | In-app and push for everyone. Email or Teams for managers. |

### PMS: manual or the beta Opera link

ExpTix runs fine without a PMS. The front desk sets **Occ** or **Vac** and **Due in** or **Due out** on **Rooms**, and the board is as accurate as those taps. Minibar charges then stay in ExpTix.

The Oracle Opera Cloud (OHIP) and Canary links are **beta**. They follow the vendors' documented patterns but have not been tested against live systems. If you want them:

1. Run the integration in **Mock** mode first and train on it.
2. Switch to **Live** in a test window with your Opera administrator watching.
3. Compare room status in both systems for a week before you trust it.

## Hosting

Use MySQL 8 or MariaDB 10.6+ on your own Linux server or a hosting plan with PHP 8.1, MySQL and cron. Either takes about an hour following the deployment guide. MySQL handles many people saving at once and has standard backup tools.

Before real guest data goes in, work through the security checklist in the deployment guide. Force MFA for managers and above with `mfa_required_ranks_at_or_above` set to `70`.

## Setup in order

| Week | What | Time |
|---|---|---|
| 1 | Install, HTTPS, cron, backups, first administrator with MFA | Half a day for IT |
| 1 | Departments with a manager and email for each | 1 hour |
| 1 | Locations, room types with credits, **Rooms** | 2 to 4 hours |
| 2 | SLA policies: start from the defaults, adjust with each manager | 1 hour |
| 2 | Request categories and auto-route keywords, per department | 1 to 2 hours per department |
| 2 | Checklist templates: room inspection, opening and closing checks, fire rounds | 2 to 3 hours |
| 2 | Assets, PM schedules, parts, vendors with insurance expiry dates | 1 to 2 days for engineering |
| 3 | Food safety points and outlets, 86 list, allergy alerts | 2 hours |
| 3 | **Notification rules** | 1 hour |
| 3 | **Users**, with role and department | 1 to 2 hours |
| 3 | **Room QR codes**: print and place | Half a day |
| 4 | Pilot and go live (see the [rollout plan](playbook-rollout.html)) | 2 to 4 weeks |

Assets and PM take longest. Start with life safety and high-cost equipment and add the rest over time.

## Who does what

| Person | Role | Notes |
|---|---|---|
| General manager | General manager | Sees everything, owns **Reports** and **Announcements**. |
| Hotel IT or your IT provider | Administrator | Install, **Users**, **Integrations**, backups, **Audit log**. |
| Executive housekeeper, chief engineer, F&B manager, chef, front office manager | Department manager | Own their categories, checklists, assets, PM and purchase approvals. |
| Shift leads, inspectors | Supervisor | **Auto-assign**, **Inspections**, **Inspected**, watch the clock. |
| Front desk, PBX, night audit | Front desk / guest services | Sees all departments to answer "where is my request?" Can rush rooms and set out of order. |
| Attendants, techs, cooks, servers, officers | Staff | **My work** and **My rooms**. |
| Outside trades | Contractor | Only their own jobs. |
| Owner's rep, brand or insurance reviewer | Auditor | Read-only. |

In this version, SLA policies and **Notification rules** need the GM or an administrator to save changes. Department managers decide what they want and the GM enters or approves it. That doubles as a simple change control.

## The housekeeping board at the centre

Most of the day runs through **Rooms**.

1. **Morning:** the supervisor taps **Auto-assign**. Rooms go to attendants on shift, floors kept together, workloads shown as bars.
2. **Front desk** filters to **Due in** and taps **Rush** on early arrivals. Housekeeping sees it with no phone call.
3. **Attendants** tap **Start cleaning** and **Clean** on their phones. Gold-bordered rooms first.
4. **Supervisors** run the room inspection and tap **Inspected**. Failed lines become jobs for the right team.
5. **Engineering** sees the 🔧 on rooms with open repairs. Out-of-order rooms return to service when the job is resolved.

## A day in the life

- **06:45** Night audit adds an **Action required** note to the **Shift logbook**.
- **07:00** Day shift ticks **On shift** and reads the logbook. The kitchen logs opening temperatures.
- **08:30** A guest scans the room QR code for extra towels. The job lands on housekeeping's phones with a 20-minute timer. The guest watches it move to **Completed**.
- **10:00** The chief engineer clears overnight jobs and today's PM from **My work**.
- **13:00** The GM checks the Home tiles. One department shows overdue jobs. The GM calls its manager.
- **15:00** Check-in. Front desk works from green rooms only.
- **18:00** A banquet BEO created last week has given setup, kitchen and AV their tasks. Each team ticks its own.
- **22:00** Evening shift logs a noise complaint with service recovery: what was given back and what it cost.

## Reports to check weekly

Open **Reports** with 7 days for the weekly meeting and 30 days for the monthly one. Print with 🖨.

| Report | Who | Question it answers |
|---|---|---|
| Response and resolve times by department | GM, managers | Who is keeping up? |
| SLA misses by priority | Managers | Are the timers realistic, or are we short-staffed? |
| Guest ratings and complaints | GM, front office manager | Are guests happier this month than last? |
| Service recovery value | GM, controller | What are we giving back, and why? |
| Repeat-problem rooms | Chief engineer | Which rooms need a real fix? |
| Costly machines | Chief engineer, GM | Where do labour and parts go? Repair or replace? |
| PM on time | Chief engineer | Are we keeping up with planned work? |
| Inspections and scores | Executive housekeeper | Who needs coaching, who deserves thanks? |

Do not set savings targets from day one. Take four weeks of numbers as your baseline, then measure change against it.

## Common pitfalls

- **Too many categories.** Twenty quick picks per department slows people down. Start with ten.
- **Alerts to everyone.** One rule that pushes every new job to every manager trains people to ignore the bell. Use department and minimum priority on each rule.
- **On shift left ticked.** People get alerts on their day off. Make unticking part of going home.
- **No credits on room types.** **Auto-assign** cannot balance workloads without them.
- **Trusting the beta Opera link on day one.** Run it in **Mock** mode, then compare live data for a week.
- **Paper running alongside forever.** Set a date when the clipboard stops (see the [rollout plan](playbook-rollout.html)).

## When to grow

Move to the [large hotel and resort playbook](size-large-hotel.html) when you pass about 250 rooms, add a second building or several outlets, or need contractors, auditors and a BI feed as everyday parts of the system. If you are opening a second property, read the [group and chain playbook](size-group-chain.html). Each property needs its own install today.
