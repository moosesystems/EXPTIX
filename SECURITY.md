# Security policy

## Reporting a vulnerability

Please do not open a public issue for security problems.

Use GitHub's private reporting instead: open the **Security** tab of this repository and choose **Report a vulnerability**. Include the version or commit, the steps to reproduce, and the impact you expect. We aim to acknowledge reports within five working days and to agree a disclosure date with you.

## Supported versions

Only the latest commit on `main` receives security fixes while the project is pre-1.0.

## Scope

In scope: the code in this repository, its default configuration and its documented deployment. Out of scope: weaknesses that need a misconfigured server (for example, serving `app/` from the web root), third-party services ExpTix connects to, and findings against the fictional demo data.

## Hardening

The deployment guide (`docs/DEPLOYMENT.md`) has a security checklist for production installs.
