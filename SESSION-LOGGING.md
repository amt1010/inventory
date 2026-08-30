# Session Logging Instructions

> Drop this file into any repo. If the repo already has a `CLAUDE.md`, add one line there:
> `@SESSION-LOGGING.md` (Claude Code auto-imports files referenced this way).
> If there's no `CLAUDE.md` yet, rename this file to `CLAUDE.md` or keep both.

## Purpose

Every working session in this repo (bug fix, feature build, refactor, investigation)
should leave behind a durable, human-readable record — separate from git commit
messages — so future sessions (by Claude or a teammate) can quickly answer:
*"what happened here, why, and what's left?"*

## When to run the logging script

Run `scripts/log-session.sh` in these situations, without waiting to be asked:

1. **At the end of a working session** — when the user signals they're wrapping up
   ("that's it for today", "let's stop here", "looks good, done"), or a natural
   milestone is reached (feature complete, bug fixed, PR opened).
2. **On explicit request** — user says "log this session", "save session history",
   "record what we did", or similar.
3. **Before a risky or destructive action** — e.g. before a large refactor or
   force-push, so there's a checkpoint of what led up to it.

Do not run it after every trivial exchange — one entry per meaningful session is
the goal, not one per message.

## What the script does

`scripts/log-session.sh` appends a single dated entry to `docs/session-history/SESSION_LOG.md`
capturing:

- Timestamp and git branch
- Current commit hash and short status (`git status --short`)
- Files changed since the last log entry (`git diff --stat` against the last
  logged commit, when available)
- A free-text summary block that Claude fills in before running the script

## How to invoke it

```bash
./scripts/log-session.sh "<one-line session summary>" <<'EOF'
## Goal
What this session set out to do.

## Changes
- Bullet list of concrete changes made (files, features, fixes)

## Decisions
- Any non-obvious choices made and why

## Open items / next steps
- What's left, known issues, follow-ups
EOF
```

If the heredoc body is omitted, the script logs the one-line summary only —
still better than nothing, but prefer the full block when the session was
non-trivial.

## Setup (one-time per repo)

Create `scripts/log-session.sh` with the following content and `chmod +x` it:

```bash
#!/usr/bin/env bash
# scripts/log-session.sh — append a session record to docs/session-history/SESSION_LOG.md
set -euo pipefail

LOG_DIR="docs/session-history"
LOG_FILE="$LOG_DIR/SESSION_LOG.md"
mkdir -p "$LOG_DIR"

SUMMARY="${1:-"(no summary provided)"}"
TIMESTAMP=$(date -u '+%Y-%m-%d %H:%M UTC')
BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "n/a")
COMMIT=$(git rev-parse --short HEAD 2>/dev/null || echo "n/a")
STATUS=$(git status --short 2>/dev/null || echo "")

# Body comes from stdin (heredoc) if piped in, else empty
BODY=""
if [ ! -t 0 ]; then
  BODY=$(cat)
fi

{
  echo ""
  echo "---"
  echo ""
  echo "## $TIMESTAMP — $SUMMARY"
  echo ""
  echo "- Branch: \`$BRANCH\`"
  echo "- Commit: \`$COMMIT\`"
  if [ -n "$STATUS" ]; then
    echo "- Working tree at log time:"
    echo '```'
    echo "$STATUS"
    echo '```'
  fi
  if [ -n "$BODY" ]; then
    echo ""
    echo "$BODY"
  fi
} >> "$LOG_FILE"

echo "Session logged to $LOG_FILE"
```

Initialize the log file once so the repo has a stable target:

```bash
mkdir -p docs/session-history
cat > docs/session-history/SESSION_LOG.md <<'EOF'
# Session History

Append-only log of working sessions in this repo. Newest entries at the bottom.
Generated/maintained via `scripts/log-session.sh` — see SESSION-LOGGING.md.
EOF
```

## Ground rules

- **Append-only.** Never edit or delete past entries — if something was wrong,
  add a new entry correcting it.
- **No secrets.** Never log credentials, API keys, tokens, or customer data in
  the summary or decisions text.
- **Commit the log file.** `docs/session-history/SESSION_LOG.md` is checked into
  git like any other doc — it's part of the repo's history, not a local scratch file.
- **One entry per session**, not per turn or per commit.
