# AGENTS.md — S.LEAGUE NOW

This repository follows the global governance in `oosaka0123-sudo/ai-master`.

## Project Source of Truth

- GitHub current code / Issue / PR / Commit / Actions are the project SSOT.
- Chat history and model memory are reference only.
- Do not recreate deployed files from memory when the verified current source is unavailable.

## Change Rules

- Prefer small, reviewable changes on a dedicated branch and PR.
- Preserve existing working layout, ranking, schedule, event, contact, and routing behavior unless the task explicitly requires change.
- When adding a new top-level public page, check the root `.htaccess` routing whitelist in the same task before production verification.
- Do not modify unrelated pages or CSS during a narrow fix.

## Data / Secret Boundary

Do not commit secrets, credentials, passwords, tokens, cookies, private keys, or production-only confidential configuration.

Runtime-generated data such as ranking/schedule caches should not be treated as hand-maintained source. If a generated file is required for local development, prefer a documented sample/schema rather than production data.

## Deployment Boundary

Production upload / FTP / SFTP, destructive production data changes, secret or IAM changes, billing changes, and irreversible operations require explicit human approval.

## Evidence

Do not report completion from code edits alone. Verify the applicable scope with syntax/test/review/PR/merge and, when relevant, production URL behavior.
