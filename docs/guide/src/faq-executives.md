---
title: Executive questions
subtitle: Cost, data, risk, support and how to try it
audience: Owners, CEOs, CFOs, VPs and general managers
kind: faq
group: faq
order: 30
lang: en
---

## What does ExpTix cost?

There is no licence fee and no per-user or per-room charge. You pay for hosting, setup time, training and support, whether from your own IT or a provider you choose. Small properties run on ordinary shared hosting; large ones need their own server.

## Will it save us money?

We do not promise savings. ExpTix shows you where money goes: labour and parts per job and per machine, service recovery cost, repeat-problem rooms and PM on time. Measure your own results over a pilot and decide on those numbers.

## Who owns the data?

You do. ExpTix runs on your server or your provider's, and the data sits in your own database. Nobody else holds a copy unless you send it somewhere.

## What guest data does it store?

Only what the work needs: guest name, room, confirmation number, contact details when a guest gives them, preferences and allergy notes. It does not store payment card numbers; minibar charges are recorded as amounts. When a room goes vacant after departure, ExpTix clears the guest name and reservation details from the room.

## What about privacy law?

You choose where it is hosted, so you can meet your data residency rules. Before going live, agree guest data retention and a privacy notice for the QR portal. The deployment guide's checklist covers this.

## Is it secure?

It ships with strong password hashing (Argon2id), lockout after 5 wrong tries, optional or forced two-step sign-in, session time-outs, scoped API tokens, encrypted integration secrets and a hash-chained audit log. The controls are mapped to NIST SP 800-53 Rev. 5 and SP 800-63B on a built-in page. Security also depends on how you host it: run a vulnerability scan or penetration test before real guest data goes in.

## Who supports it?

There is no vendor support contract. Support comes from your own IT, an IT provider of your choice, or the open-source community. Decide this before you pilot.

## What does the AGPL licence mean for us?

You may use, change and share ExpTix, including commercially. Using it unmodified only requires keeping the copyright and licence notices. If you change the code and let people use your changed version over a network, you must offer those users your changed source code.

## How big a property can it handle?

It has been tested with a 182-room demo hotel. A property of 500 or more rooms should run MySQL on a proper server and pilot one or two departments first.

## Can one install run our whole group?

Not yet. One install runs one property. A group runs one install per property, with the same templates and central reporting through the API or CSV into your BI tool. Multi-property in one install is on the roadmap.

## What is on the roadmap?

Multi-property installs, single sign-on (Entra ID, Google), translations of the app, live door lock and building system connectors, and a Docker image. These are plans, not dates.

## Does it work with our PMS?

ExpTix runs without a PMS. The Oracle Opera Cloud and Canary links are beta: written to the vendors' documented patterns and not yet tested against live systems. Every link has a mock mode for safe testing.

## How do we pilot it?

Install it, load the demo hotel and take the 10-minute GM tour. Then set up one or two departments on a fresh database and run them for 90 days. Measure with **Reports** at 30, 60 and 90 days against your baseline.

## What if we stop using it?

Your data is in your own database. Export the ticket list to CSV from **Reports**, or pull any records through the API. You keep the database itself, too.

## Is it ready?

Version 0.9 is feature complete for one property and ready for pilots. Treat the first property as a pilot, not a full rollout.
