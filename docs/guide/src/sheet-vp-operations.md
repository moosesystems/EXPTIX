---
title: VP of operations
subtitle: One operating model across every property
audience: VPs of hospitality operations and regional directors for a group or chain
kind: sheet
group: leaders
order: 50
lang: en
---

## Recommendation

Standardise the rules first. Pilot at one property, lock the templates, then roll out in waves. Same categories, SLAs and checklists everywhere means the numbers compare.

## The honest fact: one install per property

ExpTix runs one property per install today. Multi-property in one install is on the roadmap, not built. A 12-hotel group runs 12 installs, with one operating model:

- **Same rules everywhere:** one master set of departments, request categories (with routing keywords), SLA policies, checklist templates and PM schedules.
- **Copying templates:** there is no export button for templates yet. Your IT team copies them between installs through the REST API, or someone re-enters them from the master list.
- **Central reporting:** each install exposes its reports and records through the API and a ticket CSV export. Feed them into your BI tool for a portfolio view.
- **Naming conventions:** the same department codes, category and SLA names at every property, so reports line up.

## Brand-standard inspections

Build your brand standard as checklist templates. Every ✕ becomes a job, scores appear in **Reports**, and brand reviewers get read-only **Auditor** accounts.

## KPIs to compare properties

| KPI | Where |
| --- | --- |
| Average response and resolve time | **Reports**, by department and priority |
| SLA misses | **Reports** |
| Guest rating (out of 5) and complaints | **Reports**, guest section |
| Service recovery cost | **Reports**, guest section |
| PM on time | **Reports**, PM |
| Inspection average score and passes | **Reports**, inspections |
| Repeat-problem rooms | **Reports** |

## Phased rollout

1. **Pilot property (90 days):** one or two departments first, usually Housekeeping and Engineering. Pilot integrations in mock mode.
2. **Lock the master templates** from what the pilot taught you.
3. **Wave 1:** two or three properties with strong local managers.
4. **Wave 2 and later:** the rest, in groups your IT team can support.

## Governance

- **Template owner:** one person (often in your office) owns the master categories, SLAs and checklists.
- **Change control:** properties propose changes; the owner approves, updates the master list, and pushes it to every install on a set schedule.
- **Local freedom:** properties may add local categories, never rename shared ones.

## Honest limits

- No single sign-on yet (Entra ID and Google are on the roadmap). Each install has its own user accounts.
- Opera Cloud and Canary links are beta and untested against live systems.
- The screens are English only today.
- No vendor support contract: plan your own IT or provider support across the portfolio.
