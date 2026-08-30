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
