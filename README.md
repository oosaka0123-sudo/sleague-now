# S.LEAGUE NOW

S.LEAGUE surfing information site project.

## Source of Truth

This repository is the project SSOT. Current implementation, issues, pull requests, commits, and project-local documentation take precedence over chat history or model memory.

## Current state

The verified current S.LEAGUE NOW source has been imported to `main` from the sanitized production ZIP.

Current source areas include:
- `public/` — HOME / RANKING / SCHEDULE / EVENT / CONTACT pages
- `inc/` — shared configuration, parsers, view/SEO/status/video helpers
- `cron/` — data fetch/update scripts
- `assets/` — shared CSS
- root `.htaccess` / `robots.txt` / Search Console verification file

Runtime-generated data, logs, local-only helpers, production-only filesystem wrappers, credentials, secrets, and temporary test files are intentionally excluded from source control.

## Production

Production URL: `https://sleague.rss7.net/`

Production deployment is currently manual. Do not treat a GitHub merge as proof of production deployment; verify the live URL separately when a task changes runtime behavior.

## Change rules

Read `AGENTS.md` before changes. Preserve currently working ranking, schedule, event, contact, routing, parser/cron behavior, and stable event-card layout unless the task explicitly requires modification.
