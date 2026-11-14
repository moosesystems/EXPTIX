# Security policy

## Reporting a vulnerability

Please do not open a public issue for security problems.

Use GitHub's private reporting instead: open the **Security** tab of this repository and choose **Report a vulnerability**. Include the version or commit, the steps to reproduce, and the impact you expect. We aim to acknowledge reports within five working days and to agree a disclosure date with you.

## Supported versions

Only the latest commit on `main` receives security fixes while the project is pre-1.0.

## Scope

In scope: the code in this repository, its default configuration and its documented deployment. Out of scope: weaknesses that need a misconfigured server (for example, serving `app/` from the web root), third-party services ExpTix connects to, and findings against the fictional demo data.

## Server runtime

ExpTix runs on PHP, so PHP's own security fixes matter as much as ours. Use a supported PHP branch with current security releases. For the October 2026 PHP2Shell flaws (CVE-2026-109618, CVE-2026-109619) that means 8.2.35, 8.3.36, 8.4.27 or 8.5.12 or newer. The **Security and compliance** page (control SI-2) and `php app/bin/cli.php check-php` report whether the server's PHP has those fixes. PHP 8.1 and older get no fix from the PHP project.

## Hardening

The deployment guide (`docs/DEPLOYMENT.md`) has a security checklist for production installs.
