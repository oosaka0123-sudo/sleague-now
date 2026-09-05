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

## Chat persistence / knowledge routing

When the user says `このチャット内容をリポジトリに保存して` or gives an equivalent instruction, do not copy the raw conversation log into the repository. Normalize only durable or restart-critical information and route it to the existing Project source of truth.

- Before writing, re-read the current default branch and the relevant canonical files, plus related Issue / PR / Actions when needed.
- Current confirmed project specification, routing behavior, page structure, data behavior, and operating constraints -> update the existing `README.md` or other clearly responsible canonical document in place.
- Important long-lived design rationale -> create or update `DECISIONS.md` only when such a decision actually exists.
- Reusable operational or recovery procedure -> create or update `RUNBOOK.md` only when a reusable procedure actually exists.
- Unfinished work needed by the next AI/session -> use the Project handoff location defined by `ai-master`; if no dedicated location exists, use `HANDOFF.md`. Create it only when needed.
- Work history already recoverable from code, Issue, Pull Request, Actions, or Commit history -> do not duplicate it into Markdown.
- Prefer replacing stale current-state text over append-only chat summaries.
- Never save secrets, credentials, passwords, tokens, cookies, private keys, production-only confidential configuration, or private runtime data as chat persistence.
- After saving, report which canonical files changed and what was intentionally not duplicated.

## Data / Secret Boundary

Do not commit secrets, credentials, passwords, tokens, cookies, private keys, or production-only confidential configuration.

Runtime-generated data such as ranking/schedule caches should not be treated as hand-maintained source. If a generated file is required for local development, prefer a documented sample/schema rather than production data.

## Deployment Boundary

Production upload / FTP / SFTP, destructive production data changes, secret or IAM changes, billing changes, and irreversible operations require explicit human approval.

## Evidence

Do not report completion from code edits alone. Verify the applicable scope with syntax/test/review/PR/merge and, when relevant, production URL behavior.
