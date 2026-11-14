---
title: Small lodge or inn
subtitle: 3 to 20 rooms, a few people who do everything
audience: Owners and managers of lodges, inns, motels and small B&Bs
kind: playbook
group: size
order: 10
lang: en
---

## Who this is for

You run 3 to 20 rooms with a small team. Picture a three-room fly-in lodge with two staff who cook, clean, fix the generator and greet the floatplane. Nobody has time for software. You want one list of what needs doing, a record of what was done, and maybe a QR card so guests can ask for towels without hunting you down.

Use ExpTix lightly. Switch on what saves you a trip or a forgotten job, and leave the rest off. You can switch more on later without starting again.

## What to switch on and what to leave off

| Area | Recommendation | Why |
|---|---|---|
| Departments | Keep 3 or 4: Front office, Housekeeping, Engineering (call it Maintenance if you like), Kitchen. Untick **Active** on the rest. | Fewer departments means fewer places for a job to hide. |
| Housekeeping board | On. | Even with three rooms, red, blue and green tells the other person what is ready. |
| Food safety | On if you serve food. | Fridge and freezer readings in seconds, and a clean log for the health inspector. |
| Preventive maintenance | On, with a short list: generator service, water treatment, smoke alarm test, chimney or stove check. | Remote sites cannot wait for a repair person. PM jobs remind you before things fail. |
| Guest QR portal | Optional. | Worth it if guests are spread across cabins. Skip it if guests eat with you and just ask. |
| PMS link (Opera) | Off. | You run fine without one. Set **Occ** or **Vac** and **Due in** or **Due out** by hand on **Rooms**. |
| Canary, email to ticket, sensors | Off at first. | Each one is something else to look after. |
| Notifications | In-app and web push only. | No SMS or email setup needed. |
| Events and BEOs, Key control, purchase requests | Off or ignored. | Turn them on if you host groups or hand out master keys. |

Without a PMS, the board is only as accurate as your taps. Make "tap the room when a guest arrives or leaves" part of the routine.

## Hosting: keep it simple

Use SQLite on ordinary shared web hosting that offers an up-to-date PHP 8.2+ and cron. SQLite needs no database setup and is fine for a small property with a few dozen staff. The deployment guide walks through shared hosting in about an hour.

You need three things whatever you pick: HTTPS, the cron job running every minute, and `demo_mode` set to `false`. Without cron, SLA timers and reminders stall overnight.

### Limited internet

ExpTix needs a connection to the server to save work. If the link drops, staff see that they are offline. Write the job down and log it when the link is back.

On a satellite or slow link, keep it light:

1. Use phones on the lodge Wi-Fi, installed from the browser with **Add to Home Screen**.
2. Take photos only when they help. Every photo is an upload over your link.
3. Skip integrations you do not need. Each one adds traffic.

### A small computer at the lodge instead

You can run ExpTix on a small computer at the lodge so staff on the local Wi-Fi keep working when the outside link is down. Only do this if someone can maintain it. The trade-offs:

- **For it:** staff can log and finish jobs on the local network during an outage.
- **Against it:** you own the power, updates, backups and HTTPS certificate. Push alerts still need the internet, because they travel through the phone makers' push services. Guests outside your Wi-Fi cannot reach the QR page. A failed disk with no off-site backup loses the record.

For most small lodges, ordinary hosting with a written fallback is the safer choice.

## Setup in order

| Step | What | Time |
|---|---|---|
| 1 | Hosting, install, `create-admin`, HTTPS, cron (see the deployment guide) | About 1 hour |
| 2 | Sign in, change the temporary password, turn on MFA in **My account** | 5 minutes |
| 3 | Departments: switch off the ones you do not need, rename the rest | 10 minutes |
| 4 | **Users**: one account per person. Never share a sign-in | 10 minutes |
| 5 | Room types and **Rooms** | 15 minutes |
| 6 | SLA policies: keep the defaults, loosen the ones you cannot meet | 10 minutes |
| 7 | Request categories: keep a short list of quick picks | 20 minutes |
| 8 | Checklist templates: one room check, one opening or closing check | 20 minutes |
| 9 | PM schedules for the 4 or 5 things that would hurt most if they failed | 30 minutes |
| 10 | Food safety points, if you serve food | 15 minutes |
| 11 | Each person: **Add to Home Screen**, **Enable push alerts**, **Send a test** | 10 minutes each |
| 12 | Optional: **Room QR codes**, print and place | 20 minutes |

Plan on one afternoon, plus the hosting hour.

### SLAs for a small team

The demo timers assume a staffed hotel (a guest amenity in 20 minutes, a Critical response in 5). Two people cannot always meet those. Set timers you can keep, and keep Critical tight for safety. A breached timer should mean something went wrong, not that someone was out on the dock.

## Who does what

At a lodge, one person wears several hats. Give each person the lowest role that covers what they do.

| Person | Role in ExpTix | Departments |
|---|---|---|
| Owner or manager | General manager (also the administrator) | All |
| Second staff member | Supervisor | Housekeeping, also works in Kitchen and Maintenance |
| Seasonal help | Staff | Their main area, plus **Also works in** for the rest |
| Visiting trades (generator, water, propane) | Contractor | Maintenance only |

The GM role covers **Users**, **Integrations** and **API and MCP**, so a separate administrator account is optional. If you keep one, use it only for setup.

Only Front desk and higher roles can change **Occ**, **Vac**, **Due in** and **Due out**, and only Supervisor and higher can mark a room **Inspected**. Whoever checks guests in needs at least the Front desk role.

Assign every user to every department they help with, using **Also works in**. Otherwise they will not see jobs for that team.

## A day in the life

1. **Morning:** both staff tick **On shift**. The manager reads the **Shift logbook** and taps **Mark read**.
2. **Kitchen opening:** temperatures go in with **Save reading**. A bad reading opens an urgent job by itself.
3. **Guests leave:** the room is marked **Due out**, then **Start cleaning** and **Clean**. The manager checks it and taps **Inspected**.
4. **Something breaks:** whoever sees it taps **+ New**, types "shower drain slow in cabin 2", adds a photo. It goes to Maintenance.
5. **The generator PM comes up:** it appears in **My work** with a checklist. Tick, **Log time and parts**, **Resolve**.
6. **Evening:** one line in the **Shift logbook** for tomorrow. Both untick **On shift**.

## Reports to check weekly

Open **Reports**, pick 7 days. Ten minutes is enough.

- **Open and overdue jobs:** anything older than a week needs a decision.
- **Repeat-problem rooms:** the same cabin keeps coming up? Fix the cause.
- **PM on time:** missed PM at a remote site costs more than anywhere else.
- **Parts used:** reorder before the next supply run.
- **Guest ratings,** if you use QR codes.

## Common pitfalls

- **Switching everything on.** More screens means more to ignore. Start small.
- **No cron.** Timers and PM jobs stop. Check it in week one.
- **Shared sign-ins.** ExpTix writes a name on every action. Shared accounts make the record useless.
- **Forgetting room status.** Without a PMS, nobody else updates Occ, Vac, Due in and Due out.
- **No backup.** Run `cli.php backup` daily and copy it off the server. Keep `app.key` safe too.
- **Expecting support from a vendor.** ExpTix is free software with no support contract. Know who your IT person is before you need them.

## When to grow

Move to the [mid-size hotel playbook](size-mid-hotel.html) when:

- you have more than about 20 rooms or a few dozen staff,
- you hire department heads who need their own SLAs and checklists,
- several people update ExpTix at the same moment all day.

At that point, move to MySQL. There is no built-in converter yet: export each table to CSV and import it into a freshly migrated MySQL database, or start fresh at a quiet time.
