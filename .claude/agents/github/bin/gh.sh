#!/usr/bin/env bash
# Wrapper for the GitHub CLI that loads GH_TOKEN from .credentials.local
# and execs `gh "$@"`.
#
# Usage:
#   bash bin/gh.sh pr view 123
#   bash bin/gh.sh issue list --state open --limit 20
#   bash bin/gh.sh api repos/owner/repo/pulls
#   bash bin/gh.sh api -X POST repos/owner/repo/issues -f title="Bug" -f body="Repro: ..."
#   bash bin/gh.sh release create v1.2.3 --notes "release notes"

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
AGENT_DIR="$(dirname "$SCRIPT_DIR")"
CRED_TEMPLATE="$AGENT_DIR/.credentials"
CRED_LOCAL="$AGENT_DIR/.credentials.local"

# --- Handle --help / -h before any credential checks ---
for arg in "$@"; do
    case "$arg" in
        --help|-h)
            sed -n '2,10p' "$0" | sed 's/^# \?//'
            exit 0
            ;;
    esac
done

# --- Sentinel integrity ---
EXPECTED_SENTINEL="GH_TOKEN=DO-NOT-CHANGE-THIS-LINE-CHANGE-DOT-LOCAL-FILE"
ACTUAL_SENTINEL="$(cat "$CRED_TEMPLATE" 2>/dev/null || true)"
if [ "$ACTUAL_SENTINEL" != "$EXPECTED_SENTINEL" ]; then
    echo "ERROR: .credentials sentinel tampered. Run 'bash $SCRIPT_DIR/preflight.sh' for details." >&2
    exit 2
fi

# --- Local credentials file ---
if [ ! -f "$CRED_LOCAL" ]; then
    echo "ERROR: $CRED_LOCAL missing. Run 'bash $SCRIPT_DIR/preflight.sh' for setup instructions." >&2
    exit 2
fi

TOKEN_VAL="$(grep -E '^GH_TOKEN=' "$CRED_LOCAL" | head -n1 | cut -d= -f2-)"
if [ -z "$TOKEN_VAL" ]; then
    echo "ERROR: GH_TOKEN is empty in $CRED_LOCAL. Run 'bash $SCRIPT_DIR/preflight.sh' for help." >&2
    exit 2
fi

# --- gh CLI availability ---
if ! command -v gh >/dev/null 2>&1; then
    {
        echo "ERROR: 'gh' CLI is not installed on this host."
        echo "Install instructions: https://github.com/cli/cli#installation"
        echo "(In Dockraft PROD containers 'gh' is deliberately omitted; install it on the host"
        echo " or run this agent from a DEV container where DKZ_ENV=DEV installs gh.)"
    } >&2
    exit 2
fi

# --- Execute ---
# Export only for the duration of this script - never persists in the parent shell.
export GH_TOKEN="$TOKEN_VAL"
exec gh "$@"
