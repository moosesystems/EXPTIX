---
title: Rollout plan
subtitle: 30, 60 and 90 days from install to everyday use
audience: GMs, department managers, champions and IT at any size of property
kind: playbook
group: size
order: 50
lang: en
---

## The plan in one line

Pick champions, set it up, pilot one department, train with the one-page sheets, go live, then measure. Do not switch the whole property on in one day.

This plan works at any size. A small lodge will move faster. A large hotel will repeat days 31 to 60 for each department.

## Days 1 to 30: prepare

### Pick champions

Choose one champion per department: a respected frontline person or supervisor who likes trying things, not only the manager. Champions:

- help set up their department's quick picks and checklists,
- learn it first and answer "how do I" questions on shift,
- tell you what is not working, honestly.

Give them time for it on the schedule.

### Set it up

1. **Install.** IT follows the deployment guide: HTTPS, cron, backups, `demo_mode` off, the first administrator with MFA.
2. **Explore the demo.** Load the demo hotel on a separate test install so managers can click around without risk. Start the real system on a fresh database.
3. **Configure,** in this order: departments, users, locations, room types and rooms, SLA policies, request categories, checklist templates, assets and PM, food safety points, notification rules, room QR codes. The playbook for your size gives time estimates.
4. **Pick the pilot department.** Choose one with clear work and a willing manager. Engineering is often a good first choice. Housekeeping and front office go together, because the board needs both.
5. **Set a baseline.** Write down how you track work today and what you know about it (for example, how many guest complaints last month). You will compare against ExpTix numbers later.

## Days 31 to 60: pilot one department

1. **Train the pilot team** (see the training plan below).
2. **Go live in the pilot department only.** Other departments keep their old way for now, but anyone can report a job to the pilot team with **+ New**.
3. **Stand-up each morning, 10 minutes.** What went wrong yesterday? Fix categories, keywords, checklists and SLAs as you go.
4. **Watch the numbers weekly.** **Reports**, 7 days: open and overdue jobs, SLA misses, unassigned jobs.
5. **Tune notification rules.** Ask the team which alerts they ignore. Remove or narrow those rules.
6. **Decide by day 60.** Is the pilot team working from ExpTix without paper? If yes, move on. If no, find out why and fix it first.

## Days 61 to 90: go live and measure

1. **Bring on the next departments,** one or two at a time.
2. **Put the guest QR codes out** once housekeeping and front office are live.
3. **Retire the old habits** (see below).
4. **Measure.** Use 30-day **Reports** for response and resolve times, SLA misses, guest ratings, repeat-problem rooms and PM on time. Compare with your baseline. Report what changed, not what you hoped.
5. **Hand over.** Name the person who owns ExpTix day to day, and their backup.

## Training plan by role

Use the one-page sheets from this library. Keep sessions short and on the floor, with phones in hand. Each person installs ExpTix with **Add to Home Screen**, turns on push in **My account** and taps **Send a test** before the session ends.

| Role | Sheets | Minutes |
|---|---|---|
| Everyone, first day | [First day](sheet-first-day-everyone.html) | 15 |
| Room attendants | [Room attendant](sheet-room-attendant.html) | 20 |
| Housepersons and laundry | [Houseperson and laundry](sheet-houseperson-laundry.html) | 15 |
| Maintenance techs | [Maintenance tech](sheet-maintenance-tech.html) | 30 |
| Cooks and stewards | [Cook and steward](sheet-cook-steward.html) | 20 |
| Servers and F&B | [Server and F&B](sheet-server-fb.html) | 15 |
| Front desk | [Front desk agent](sheet-front-desk-agent.html) | 45 |
| Security officers | [Security officer](sheet-security-officer.html) | 20 |
| Contractors | [Contractor](sheet-contractor.html) | 10 |
| Supervisors | [Supervisor](sheet-supervisor.html) | 45 |
| Department managers | [Department manager](sheet-department-manager.html) | 60, plus setup time |
| GM | [General manager](sheet-general-manager.html), the 10-minute GM tour in the staff guide | 30 |
| Administrator | [IT administrator](sheet-it-admin.html), the deployment guide | Half a day |

The minutes are a planning guide, not a test. Room attendant and first-day sheets also come in French, Spanish and Tagalog. The app screens themselves are English only today, so point to the button names in **bold** on the sheet while people tap.

A good session: show it once, have them do it twice, then let them report a real job.

## Go-live checklist

Tick every line before a department goes live.

- [ ] `demo_mode` is `false`, HTTPS works, cron runs every minute.
- [ ] Daily backup runs and is copied off the server. A restore has been tested.
- [ ] Managers and above have MFA on.
- [ ] Every person in the department has their own account, with the right role and departments.
- [ ] Everyone has installed it on their phone and received a test push.
- [ ] Request categories and keywords route test jobs to the right team.
- [ ] SLA policies have been agreed with the department manager.
- [ ] Notification rules have been tested: the right people get the right alerts, and nobody gets all of them.
- [ ] Checklists and PM schedules for the department are entered.
- [ ] For housekeeping: room types have credits, and **Auto-assign** has been tried.
- [ ] For the guest portal: a test request from a printed room QR code reached the right team.
- [ ] Integrations in use are tested in **Mock** mode. Beta links (Opera, Canary) are not live without a supervised test.
- [ ] The champion knows who to call when something goes wrong.
- [ ] Everyone knows that in a real emergency they call 911 and use the radio first.

## Retiring the radio and the clipboard

People trust what they already use. Replace it step by step, and say why.

1. **Explain the reason.** Jobs get lost between shifts. ExpTix makes sure every job has an owner, a deadline and a record. It is a safety net, not a way to watch people.
2. **Run both for a short, fixed time.** Two weeks at most. Paper running alongside forever means two systems and twice the work.
3. **Set the date and keep it.** "From Monday, the work order clipboard goes." Announce it on **Announcements** and in the **Shift logbook**.
4. **Keep the radio for what radios are good at.** Emergencies and "come here now". The rule: if it needs doing later, it goes in ExpTix.
5. **Make logging easier than not logging.** A front desk agent who gets a radio call says "I will put it in ExpTix" and does, so the caller sees it works.
6. **Thank people in public.** Use inspection scores and the reports to recognise good work.
7. **Fix complaints fast.** If a button or category annoys people, change it this week. Nothing builds trust faster.
8. **Keep the old logs.** Archive the paper you retire. Inspectors and insurers can still ask for it.

## Measure, do not promise

Do not promise savings or speed-ups before you start. Measure instead: take a baseline in the first four weeks of use, then compare month to month in **Reports**. Let the numbers make the case.
