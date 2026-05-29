#!/usr/bin/env bash
# Validates GitHub credentials and resolves repo metadata for the github agent.
# Designed to be the single source of truth so the agent never runs $(...) bash itself.
#
# Usage:
#   bash bin/preflight.sh                          # full summary, exit 0 on success
#   bash bin/preflight.sh --print REPO             # print just owner/repo
#   bash bin/preflight.sh --print HOST             # print just the host (github.com or GHE)
#   bash bin/preflight.sh --print API              # print just the REST API base URL
#   bash bin/preflight.sh --print DEFAULT_BRANCH   # print just the default branch
#   bash bin/preflight.sh --print BACKEND          # print just the backend (gh / gh-needs-auth / curl)
#   bash bin/preflight.sh --quiet                  # exit code only, no output

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
AGENT_DIR="$(dirname "$SCRIPT_DIR")"
CRED_TEMPLATE="$AGENT_DIR/.credentials"
CRED_LOCAL="$AGENT_DIR/.credentials.local"

# --- Handle --help / -h before any checks ---
for arg in "$@"; do
    case "$arg" in
        --help|-h)
            sed -n '2,12p' "$0" | sed 's/^# \?//'
            exit 0
            ;;
    esac
done

# --- Parse args ---
MODE="full"
PRINT_KEY=""
EXPECT_PRINT=0
for arg in "$@"; do
    if [ "$EXPECT_PRINT" = "1" ]; then
        PRINT_KEY="$arg"
        EXPECT_PRINT=0
        continue
    fi
    case "$arg" in
        --quiet) MODE="quiet" ;;
        --print) MODE="print"; EXPECT_PRINT=1 ;;
        --help|-h) ;; # already handled
        *)
            echo "ERROR: Unknown argument: $arg" >&2
            exit 2
            ;;
    esac
done

if [ "$MODE" = "print" ] && [ -z "$PRINT_KEY" ]; then
    echo "ERROR: --print requires a key (REPO|HOST|API|DEFAULT_BRANCH|BACKEND)" >&2
    exit 2
fi

# --- 1. Sentinel integrity check ---
EXPECTED_SENTINEL="GH_TOKEN=DO-NOT-CHANGE-THIS-LINE-CHANGE-DOT-LOCAL-FILE"
ACTUAL_SENTINEL="$(cat "$CRED_TEMPLATE" 2>/dev/null || true)"
if [ "$ACTUAL_SENTINEL" != "$EXPECTED_SENTINEL" ]; then
    {
        echo "!!! CREDENTIAL TEMPLATE COMPROMISED !!!"
        echo
        echo "File:     $CRED_TEMPLATE"
        echo "Expected: $EXPECTED_SENTINEL"
        echo "Actual:   ${ACTUAL_SENTINEL:-(missing or empty)}"
        echo
        echo "This file is committed to the repository and MUST stay as the sentinel."
        echo "If a real GitHub token was written here, it is now in git history and must be REVOKED:"
        echo
        echo "  1. Open https://github.com/settings/tokens (and /settings/applications for OAuth Apps)"
        echo "  2. Revoke any token that may have been written here."
        echo "  3. Restore the sentinel:"
        echo "       echo -n \"$EXPECTED_SENTINEL\" > \"$CRED_TEMPLATE\""
        echo "  4. Inspect git history: git log -p -- \"$CRED_TEMPLATE\""
    } >&2
    exit 2
fi

# --- 2. Local credentials file ---
if [ ! -f "$CRED_LOCAL" ]; then
    {
        echo "ERROR: $CRED_LOCAL does not exist."
        echo
        echo "Create it with a single line:"
        echo "  GH_TOKEN=<your-github-token>"
        echo
        echo "OPTION 1 (RECOMMENDED) - Fine-grained Personal Access Token scoped to this repo:"
        echo "  1. Open https://github.com/settings/personal-access-tokens/new"
        echo "  2. Token name:        <owner/repo> / Claude Code"
        echo "     Expiration:        Never"
        echo "     Resource owner:    <owner of the repo>"
        echo "     Repository access: Only select repositories -> select this repo"
        echo "  3. Repository permissions (exactly these):"
        echo "       Contents:        Read and write"
        echo "       Issues:          Read and write"
        echo "       Pull requests:   Read and write"
        echo "       Metadata:        Read-only (auto-selected)"
        echo "       Actions:         Read-only"
        echo "       Commit statuses: Read-only"
        echo "  4. Generate token, copy the github_pat_... value, then:"
        echo "       echo -n \"GH_TOKEN=github_pat_XXX\" > \"$CRED_LOCAL\""
        echo
        echo "OPTION 2 - OAuth via gh CLI (broader scope across all your repos):"
        echo "  gh auth login -h github.com -p https -w"
        echo "  echo -n \"GH_TOKEN=\$(gh auth token)\" > \"$CRED_LOCAL\""
    } >&2
    exit 2
fi

TOKEN_VAL="$(grep -E '^GH_TOKEN=' "$CRED_LOCAL" | head -n1 | cut -d= -f2-)"
if [ -z "$TOKEN_VAL" ]; then
    echo "ERROR: GH_TOKEN is empty in $CRED_LOCAL" >&2
    exit 2
fi

# --- 3. Token validation against api.github.com (skip in quiet mode for speed?) ---
HTTP_CODE="$(curl -s -o /dev/null -w '%{http_code}' \
    -H "Authorization: Bearer $TOKEN_VAL" \
    https://api.github.com/user 2>/dev/null || echo "000")"
if [ "$HTTP_CODE" != "200" ]; then
    {
        echo "ERROR: GH_TOKEN failed authentication (HTTP $HTTP_CODE)"
        echo "The token may be expired, revoked, or have insufficient scopes."
        echo "Generate a new one (see 'bash $0' with no .credentials.local for instructions)"
        echo "and rewrite $CRED_LOCAL."
    } >&2
    exit 2
fi

# --- 4. Git probe ---
if ! command -v git >/dev/null 2>&1; then
    echo "ERROR: 'git' is not installed - this agent cannot operate without git." >&2
    exit 2
fi

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "ERROR: Not inside a git working tree." >&2
    exit 2
fi

# Prefer 'origin', fall back to the first remote
REMOTE_NAME="$(git remote 2>/dev/null | grep -x origin || git remote 2>/dev/null | head -n1 || true)"
if [ -z "$REMOTE_NAME" ]; then
    echo "ERROR: No git remote configured. Add one with: git remote add origin <url>" >&2
    exit 2
fi

REMOTE_URL="$(git remote get-url "$REMOTE_NAME" 2>/dev/null || true)"
if [ -z "$REMOTE_URL" ]; then
    echo "ERROR: Could not read remote URL for '$REMOTE_NAME'." >&2
    exit 2
fi

# Normalize SSH/HTTPS/with-or-without-.git
HOST="$(printf '%s' "$REMOTE_URL" | sed -E 's#^(git@|ssh://git@|https?://)##' | sed -E 's#[:/].*$##')"
REPO="$(printf '%s' "$REMOTE_URL" | sed -E 's#\.git$##' | sed -E 's#^(git@|ssh://git@|https?://)[^:/]+[:/]##')"

if [ -z "$HOST" ] || [ -z "$REPO" ]; then
    echo "ERROR: Could not parse repo slug from remote URL: $REMOTE_URL" >&2
    exit 2
fi

if [ "$HOST" = "github.com" ]; then
    API="https://api.github.com"
else
    API="https://$HOST/api/v3"
fi

# --- 5. Default branch (local symbolic-ref first, then remote) ---
DEFAULT_BRANCH="$(git symbolic-ref --short "refs/remotes/$REMOTE_NAME/HEAD" 2>/dev/null | sed "s#^$REMOTE_NAME/##" || true)"
if [ -z "$DEFAULT_BRANCH" ]; then
    DEFAULT_BRANCH="$(curl -s \
        -H "Authorization: Bearer $TOKEN_VAL" \
        -H "Accept: application/vnd.github+json" \
        "$API/repos/$REPO" 2>/dev/null \
        | sed -nE 's/.*"default_branch":[[:space:]]*"([^"]+)".*/\1/p' | head -n1 || true)"
fi
[ -n "$DEFAULT_BRANCH" ] || DEFAULT_BRANCH="main"

# --- 6. Backend selection ---
if command -v gh >/dev/null 2>&1 && gh auth status -h "$HOST" >/dev/null 2>&1; then
    BACKEND="gh"
elif command -v gh >/dev/null 2>&1; then
    BACKEND="gh-needs-auth"
else
    BACKEND="curl"
fi

# --- Output ---
case "$MODE" in
    quiet) exit 0 ;;
    print)
        case "$PRINT_KEY" in
            REPO)           printf '%s\n' "$REPO" ;;
            HOST)           printf '%s\n' "$HOST" ;;
            API)            printf '%s\n' "$API" ;;
            DEFAULT_BRANCH) printf '%s\n' "$DEFAULT_BRANCH" ;;
            BACKEND)        printf '%s\n' "$BACKEND" ;;
            *)
                echo "ERROR: --print key must be one of: REPO HOST API DEFAULT_BRANCH BACKEND" >&2
                exit 2
                ;;
        esac
        ;;
    full|*)
        echo "======= GitHub setup ======="
        echo "Repo:           $REPO"
        echo "Host:           $HOST"
        echo "API:            $API"
        echo "Default branch: $DEFAULT_BRANCH"
        echo "Backend:        $BACKEND"
        echo "============================"
        ;;
esac
