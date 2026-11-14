---
title: Manager questions
subtitle: Routing, timers, reports and running a team in ExpTix
audience: Supervisors, department managers and general managers
kind: faq
group: faq
order: 20
lang: en
---

## How does ExpTix decide which department gets a job?

It matches the words in the job against the **auto-route keywords** on each request category. The best match sets the department, category and default priority. Words like "flood", "smoke" or "stuck in elevator" raise the priority to Urgent.

## What happens when no keyword matches?

The job goes to the front office at Normal priority, so a person can send it on. If this happens often, add keywords to your categories.

## Who can change categories and keywords?

Department managers and above, under **Request categories**. Each category also sets the ticket type, default priority, SLA policy, checklist, and whether guests see it on the QR portal.

## Who can change the SLA timers?

The GM and administrator. Department managers can read the SLA policies and propose changes. Each policy sets respond-by, resolve-by, how long after a breach to escalate, and to whom.

## What happens when a job runs late?

At 80% of the time, ExpTix sends a warning. At the due time the job is breached. After the escalation time, it goes to the role or person on the SLA policy (in the demo, the manager or the GM).

## Who hears about what?

**Notification rules** decide: the event (job created, SLA breached, temperature out of range, incident reported and more), the department, the minimum priority, the channel and who. Department managers can read the rules; the GM or administrator saves them.

## Which channels can alerts use?

In-app bell, web push to phones, email, SMS (Twilio), Microsoft Teams, Slack and webhooks. Each person also chooses their own channels in **My account**.

## How do I give out housekeeping work?

**Rooms**, **Auto-assign**. ExpTix shares rooms between attendants on shift by room credits, keeping floors together. Adjust by hand with **Select rooms** and **Assign**.

## Does ExpTix do staff scheduling?

It has a simple staff schedule and a calendar for events, PM and scheduled work. It is not a full workforce or payroll system. **On shift** is what decides who gets new jobs.

## Can outside contractors use it?

Yes. Give them the Contractor role. They see only jobs given to them or their department, nothing about guests or other teams. They log time, parts and photos, which makes sign-off faster.

## Do we need a PMS?

No. Without a PMS link, staff set occupied or vacant, due in and due out by hand. The board is then only as accurate as those taps, and minibar charges stay in ExpTix.

## Does it work with Opera Cloud and Canary?

There are links for both, but they are beta: written to the vendors' documented patterns and not yet tested against live systems. Pilot them in mock mode first.

## What if our internet is poor or goes down?

ExpTix is a web app and needs a connection to save work. Pages are light and work on slow links, but there is no full offline mode. Keep a paper fallback for outages and log the jobs once you are back online.

## What reports do I get?

**Reports** for 7, 30 or 90 days: volume, response and resolve times, SLA misses, guest ratings, repeat-problem rooms, costly machines, PM on time, inspections, food safety readings, labour and parts. You can print it or export the ticket list to CSV.

## How do I see who read the logbook?

Each **Shift logbook** note shows read receipts. Staff tap **Mark read** at the start of a shift.

## How do guest ratings work?

When a QR portal request is done, the guest can rate it from 1 to 5 stars. A 1 or 2 alerts the front office for a callback. Averages appear in **Reports**.

## How do I record what we gave a guest back?

Open the complaint and fill in Resolution and service recovery: what you did and what it cost. The totals show in **Reports**.

## How do purchase approvals work?

Staff raise a purchase request with an amount. A department manager or higher approves or rejects it, and ExpTix records who and when. There are no dollar limits built in, so set your own policy.

## Is it in other languages?

The screens are English only today. Translations are on the roadmap. Printed guides can be translated now.

## Can we use it for more than one hotel?

One install runs one property today. A group runs one install per property, with shared templates and central reporting. Multi-property in one install is on the roadmap.
