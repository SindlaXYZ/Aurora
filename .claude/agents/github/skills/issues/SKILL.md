---
name: issues
description: Full CRUD on GitHub issues for the current repository - list, search, view, create, comment, close, reopen, label, and assign. Detects and uses `.github/ISSUE_TEMPLATE/*.md` when creating new issues. All write operations preview first and wait for explicit confirmation. Use whenever the user mentions issues, bugs, tickets, "what needs fixing", or wants to file/triage something.
---

# Issues Skill

> **STOP - read `github.md` Operating principle #2 first.** Examples below may show `$(...)`, pipes, or `&&` for human readability, but when you execute you MUST translate them to single static commands: `bash .claude/agents/github/bin/gh.sh ...` for any `gh` call, `bash .claude/agents/github/bin/preflight.sh --print KEY` for repo metadata, plain `git ...` for local git. Never use `$(...)` substitution - run separate calls and reuse the literal output.

Read and write GitHub issues for the repository. Read operations are immediate; write operations always preview and require explicit `yes`.

## Setup

Run the full setup from `skills/setup/SKILL.md` (Steps 1-6). The helpers `gh_get`, `gh_post`, `gh_patch` are required.

## Command map

| User says | Action | Section |
|---|---|---|
| "list issues", "what issues are open" | List open | [Read.list](#read-list) |
| "list all issues", "show closed too" | List all | [Read.list](#read-list) |
| "issues with label bug" | Filter by label | [Read.list](#read-list) |
| "my issues", "issues assigned to me" | Filter by assignee | [Read.list](#read-list) |
| "view issue #42", "show issue 42" | View one | [Read.view](#read-view) |
| "search issues for X" | Search keyword | [Read.search](#read-search) |
| "create an issue", "file a bug", "open an issue about X" | Create | [Write.create](#write-create) |
| "comment on #42", "add a comment to issue 42" | Comment | [Write.comment](#write-comment) |
| "close issue #42" | Close | [Write.close](#write-close) |
| "reopen issue #42" | Reopen | [Write.reopen](#write-reopen) |
| "label #42 as bug" | Add/remove labels | [Write.label](#write-label) |
| "assign #42 to me" / "to @alice" | Assign | [Write.assign](#write-assign) |

---

## <a name="read-list"></a>Read: list

```bash
# Open (default)
gh_get "repos/$GH_REPO/issues?state=open&per_page=50"

# All
gh_get "repos/$GH_REPO/issues?state=all&per_page=50"

# By label (URL-encode if it contains spaces)
gh_get "repos/$GH_REPO/issues?state=open&labels=bug"

# Assigned to current user (gh backend only; via REST use assignee=login)
case "$GH_BACKEND" in
  gh)   gh issue list --repo "$GH_REPO" --assignee "@me" --state open ;;
  curl) ME=$(gh_get "user" | sed -nE 's/.*"login":[[:space:]]*"([^"]+)".*/\1/p' | head -n1)
        gh_get "repos/$GH_REPO/issues?state=open&assignee=$ME" ;;
esac
```

> The REST `/issues` endpoint returns BOTH issues and PRs (every PR is technically an issue). Filter out PRs in client code: skip entries where `pull_request` key is present.

### Output format

Present as a table; one line per issue.

```
#    Title                              Labels         State   Assignee     Updated
---  ---------------------------------  -------------  ------  -----------  ----------
42   Fix AuroraCryptor padding bug      bug            open    @SindlaXYZ   2026-04-10
38   Add PostgreSQL JSON DQL function   enhancement    open    -            2026-03-22
```

Limit to 50 by default; offer to fetch more if the user asks ("show next page", "everything").

## <a name="read-view"></a>Read: view a single issue

```bash
N="$1"   # issue number
gh_get "repos/$GH_REPO/issues/$N"
gh_get "repos/$GH_REPO/issues/$N/comments"
```

Render in this layout:

```
Issue #N: <title>
State:     <open|closed>
Author:    @<login>
Labels:    <comma list>
Assignees: <@a, @b>
Created:   <date>
Updated:   <date>
URL:       <html_url>

--- BODY ---
<body>

--- COMMENTS (<count>) ---
@<login> on <date>:
  <text>
...
```

## <a name="read-search"></a>Read: search

```bash
# 'q' must be URL-encoded for the REST backend.
Q="bug authentication"
ENC=$(printf '%s' "$Q" | jq -sRr @uri)
gh_get "search/issues?q=repo:$GH_REPO+is:issue+$ENC"
```

If `jq` is not available, fall back to `gh search issues` when `GH_BACKEND=gh`.

---

## Template detection (used by all write operations that create issues)

Before opening the create flow, scan for issue templates:

```bash
TEMPLATES_DIR="$(git rev-parse --show-toplevel)/.github/ISSUE_TEMPLATE"
mapfile -t TEMPLATES < <(find "$TEMPLATES_DIR" -maxdepth 1 -type f \( -name '*.md' -o -name '*.yml' -o -name '*.yaml' \) 2>/dev/null)
```

- If 0 templates: proceed with a free-form title + body.
- If 1 template: use it.
- If 2+: list them with their `name:` frontmatter field and ask which to use.

When a Markdown template is selected:

1. Strip the YAML frontmatter from the body.
2. Read the frontmatter's `title:` and `labels:` and pre-fill them.
3. Show the body as the starting draft and pre-fill what you can infer from the user's request (failure message, repro steps, environment info).
4. Mark anything you couldn't fill as `<TODO: ...>`.

YAML form (`.yml`/`.yaml` issue forms) is GitHub-only; preview the rendered fields rather than the raw YAML.

---

## <a name="write-create"></a>Write: create

1. Run template detection.
2. Build the draft locally:

   ```bash
   TITLE='<value>'
   BODY=$(cat <<'EOF'
   <body, with TODO markers where applicable>
   EOF
   )
   LABELS='bug,priority/high'   # comma list, optional
   ASSIGNEES='SindlaXYZ'        # comma list, optional
   ```

3. Print the preview (write-operation contract):

   ```
   --- PREVIEW ---
   Action: Create issue in $GH_REPO
   Title:  $TITLE
   Labels: $LABELS
   Assignees: $ASSIGNEES

   Body:
   <BODY>
   --- END PREVIEW ---

   Proceed? (yes/no)
   ```

4. On `yes`:

   ```bash
   JSON=$(jq -nc \
     --arg title "$TITLE" \
     --arg body  "$BODY" \
     --argjson labels    "$(jq -Rn --arg s "$LABELS"    '$s | split(",") | map(select(length>0))')" \
     --argjson assignees "$(jq -Rn --arg s "$ASSIGNEES" '$s | split(",") | map(select(length>0))')" \
     '{title:$title, body:$body, labels:$labels, assignees:$assignees}')

   RESULT=$(gh_post "repos/$GH_REPO/issues" "$JSON")
   echo "$RESULT" | sed -nE 's/.*"html_url":[[:space:]]*"([^"]+)".*/Created: \1/p' | head -n1
   ```

   If `jq` is unavailable, hand-build the JSON; quote-escape with `python3 -c 'import json,sys;print(json.dumps(sys.stdin.read()))'`.

## <a name="write-comment"></a>Write: comment

```bash
N="$1" ; BODY="$2"
JSON=$(jq -nc --arg b "$BODY" '{body:$b}')
```

Preview:
```
--- PREVIEW ---
Action: Comment on issue #$N in $GH_REPO
Body:
$BODY
--- END PREVIEW ---
Proceed? (yes/no)
```

On `yes`:
```bash
gh_post "repos/$GH_REPO/issues/$N/comments" "$JSON"
```

## <a name="write-close"></a>Write: close

```bash
N="$1" ; REASON="${2:-completed}"   # completed | not_planned
JSON=$(jq -nc --arg s "closed" --arg r "$REASON" '{state:$s, state_reason:$r}')
```

Preview, then on `yes`:
```bash
gh_patch "repos/$GH_REPO/issues/$N" "$JSON"
```

## <a name="write-reopen"></a>Write: reopen

```bash
gh_patch "repos/$GH_REPO/issues/$N" '{"state":"open"}'
```

## <a name="write-label"></a>Write: label

Add labels (additive):
```bash
gh_post "repos/$GH_REPO/issues/$N/labels" '{"labels":["bug","needs-triage"]}'
```

Remove a single label:
```bash
case "$GH_BACKEND" in
  gh)   gh api -X DELETE "repos/$GH_REPO/issues/$N/labels/bug" ;;
  curl) curl -fsS -X DELETE \
          -H "Authorization: Bearer $GH_TOKEN_EFFECTIVE" \
          -H "Accept: application/vnd.github+json" \
          "$GH_API/repos/$GH_REPO/issues/$N/labels/bug" ;;
esac
```

## <a name="write-assign"></a>Write: assign

```bash
gh_post "repos/$GH_REPO/issues/$N/assignees" '{"assignees":["SindlaXYZ"]}'
```

To unassign, DELETE the same path with the same payload.

---

## Output and ergonomics

- Always print the final URL after a write.
- When listing, never paginate without asking - dump the first 50 and offer "next 50?".
- When the user references "the issue I just opened" or similar, remember the last-created issue number in the conversation context (no DB needed; just remember within the turn).

## Failure modes

| Symptom | Cause | Action |
|---|---|---|
| `HTTP 401`/`403` on write | Token without `repo` scope | Tell user; suggest `gh auth refresh -s repo` or new token |
| `HTTP 404` on view | Wrong issue number or private repo | Confirm `$GH_REPO` and the number |
| `HTTP 422 "Validation Failed"` on create | Label doesn't exist on repo | Drop the offending label or create it first |
| `gh: command not found` on write while `GH_BACKEND=curl` | A code path that hard-coded `gh` | Refactor to use `gh_post` / `gh_patch` |

## Self-check

```bash
gh_get "repos/$GH_REPO" | head -c 200
gh_get "repos/$GH_REPO/issues?state=open&per_page=1" | head -c 200
```

If both return non-empty JSON, the skill is operational.
