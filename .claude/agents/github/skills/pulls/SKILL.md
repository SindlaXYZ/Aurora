---
name: pulls
description: Read, list, search, view, and create pull requests on GitHub. Detects and uses `.github/PULL_REQUEST_TEMPLATE.md` (or `pull_request_template/*.md`) when opening new PRs. Resolves the base branch automatically from the repo default. All write operations preview first and wait for explicit confirmation. Use whenever the user says "PR", "pull request", "merge request", "open a PR", or asks to inspect/create one. Code review of an existing PR lives in the `code-review` skill - this skill stops at "PR opened".
---

# Pull Requests Skill

> **STOP - read `github.md` Operating principle #2 first.** Examples below may show `$(...)`, pipes, or `&&` for human readability, but when you execute you MUST translate them to single static commands: `bash .claude/agents/github/bin/gh.sh ...` for any `gh` call, `bash .claude/agents/github/bin/preflight.sh --print KEY` for repo metadata, plain `git ...` for local git. Never use `$(...)` substitution - run separate calls and reuse the literal output.

CRUD on PRs (excluding code review, which is its own skill). Same read-by-default, confirm-before-write contract as the rest of the agent.

## Setup

Run the full setup from `skills/setup/SKILL.md`. PRs always need the API.

## Command map

| User says | Action | Section |
|---|---|---|
| "list PRs", "open PRs", "what PRs are open" | List | [Read.list](#read-list) |
| "my PRs", "PRs assigned to me", "PRs I authored" | Filter | [Read.list](#read-list) |
| "PRs awaiting review" | Filter | [Read.list](#read-list) |
| "view PR #42", "show PR 42" | View | [Read.view](#read-view) |
| "create PR", "open a PR from this branch", "draft PR" | Create | [Write.create](#write-create) |
| "request review from @alice" | Add reviewer | [Write.reviewers](#write-reviewers) |
| "mark #42 ready for review", "convert from draft" | Toggle draft | [Write.ready](#write-ready) |
| "close PR #42 (without merging)" | Close | [Write.close](#write-close) |

Merging is intentionally NOT exposed here. Merges happen via the `code-review` skill's "approve + merge" flow, or by the user via the GitHub UI.

---

## <a name="read-list"></a>Read: list

```bash
# Open PRs (default)
gh_get "repos/$GH_REPO/pulls?state=open&per_page=50"

# All PRs
gh_get "repos/$GH_REPO/pulls?state=all&per_page=50"

# Filter helpers (gh-only convenience)
case "$GH_BACKEND" in
  gh)
    gh pr list --repo "$GH_REPO" --search "review-requested:@me"
    gh pr list --repo "$GH_REPO" --author "@me"
    gh pr list --repo "$GH_REPO" --base main --state open ;;
  curl)
    # Build via search API
    Q=$(printf '%s' "repo:$GH_REPO is:pr is:open review-requested:@me" | jq -sRr @uri)
    gh_get "search/issues?q=$Q" ;;
esac
```

### Output format

```
#    Title                          Author       Base       Head            Draft   CI       Updated
---  -----------------------------  -----------  ---------  --------------  ------  -------  ----------
123  Add foo to bar                 SindlaXYZ    main       feat/foo        no      passing  2026-05-10
118  Refactor cache layer           alice        develop    refactor/cache  YES     failing  2026-05-09
```

"CI" comes from each PR's `head.sha` -> `commits/<sha>/check-runs`. Skip CI column if it's expensive (more than 10 PRs); offer to fetch per-PR on request.

## <a name="read-view"></a>Read: view a single PR

```bash
N="$1"
gh_get "repos/$GH_REPO/pulls/$N"
gh_get "repos/$GH_REPO/issues/$N/comments"            # general comments
gh_get "repos/$GH_REPO/pulls/$N/comments"             # review (line) comments
gh_get "repos/$GH_REPO/pulls/$N/reviews"              # submitted reviews
gh_get "repos/$GH_REPO/commits/$(gh_get "repos/$GH_REPO/pulls/$N" | sed -nE 's/.*"sha":[[:space:]]*"([0-9a-f]+)".*/\1/p' | head -n1)/check-runs"
```

Render:

```
PR #N: <title>          [<DRAFT> if draft]
State:    <open|closed|merged>
Author:   @<login>
Base:     <base_branch>  <-  Head: <head_repo>:<head_branch>  (sha: <short>)
Created:  <date>     Updated: <date>
URL:      <html_url>

CI:
  ✓ phpunit-unit         success      2m12s
  ✗ phpstan              failure      18s    <one-line failure summary if available>
  - php-cs-fixer         queued       -

Reviews:
  @alice    APPROVED       2026-05-09
  @bob      CHANGES_REQUESTED  2026-05-08

--- BODY ---
<body>

--- COMMENTS (count) ---
<rendered comments>
```

Stop here. The `code-review` skill takes over if the user asks to actually review.

---

## Template detection (used by create)

```bash
ROOT="$(git rev-parse --show-toplevel)"
TEMPLATE=""
for cand in \
  "$ROOT/.github/PULL_REQUEST_TEMPLATE.md" \
  "$ROOT/.github/pull_request_template.md" \
  "$ROOT/docs/PULL_REQUEST_TEMPLATE.md" \
  "$ROOT/PULL_REQUEST_TEMPLATE.md"; do
  if [ -f "$cand" ]; then TEMPLATE="$cand"; break; fi
done
# Multi-template directory:
if [ -z "$TEMPLATE" ] && [ -d "$ROOT/.github/PULL_REQUEST_TEMPLATE" ]; then
  mapfile -t MULTI < <(find "$ROOT/.github/PULL_REQUEST_TEMPLATE" -maxdepth 1 -type f -name '*.md')
  # ask user which one (or default to the first)
fi
```

If a template is found, read it and pre-fill what you can infer from the diff and commits (see the create flow).

---

## <a name="write-create"></a>Write: create a PR

### 1. Resolve refs

```bash
HEAD_BRANCH=$(git symbolic-ref --short HEAD)
BASE_BRANCH="${BASE:-$GH_DEFAULT_BRANCH}"

if [ "$HEAD_BRANCH" = "$BASE_BRANCH" ]; then
  echo "ERROR: You're on $BASE_BRANCH; nothing to PR. Switch to a feature branch first." >&2
  exit 1
fi
```

If the head branch is not pushed:

```bash
if ! git ls-remote --exit-code --heads origin "$HEAD_BRANCH" >/dev/null 2>&1; then
  echo "Branch $HEAD_BRANCH is not on origin. Push first?"
  # On 'yes': git push -u origin "$HEAD_BRANCH"
fi
```

### 2. Build the title and body

Title: by default, the subject of the latest commit on the branch. Offer to use a different commit's subject if the branch has several.

```bash
TITLE=$(git log -1 --pretty=%s "$HEAD_BRANCH")
```

Body assembly:

1. If a template was detected: load it, strip frontmatter.
2. Pre-fill what you can:
   - `## Summary`: infer from commit messages between `$BASE_BRANCH...$HEAD_BRANCH`.
   - `## Type of change`: check ONE box based on commit prefixes/keywords (feat/fix/refactor/chore/docs).
   - `## Related issues`: scan commit messages for `#NNN` references.
   - `## How to test`: leave as `<TODO: ...>` unless user provides steps.
   - `## Checklist`: leave unchecked; let humans tick.
3. If no template: build a minimal body:

   ```
   ## Summary

   <2-3 sentence summary from commits>

   ## Commits

   - <sha> <subject>
   - ...

   ## Files changed

   See diff stats below or compare URL.
   ```

### 3. Preview

```
--- PREVIEW ---
Action: Open pull request in $GH_REPO
From:   <head_owner>:$HEAD_BRANCH
Into:   $BASE_BRANCH
Draft:  <yes|no>     (default: no; set yes if user says "draft" or branch is WIP)
Reviewers: <list or '(none)'>
Labels:    <list or '(none)'>

Title: $TITLE

Body:
<BODY>
--- END PREVIEW ---

Proceed? (yes/no)
```

### 4. Create

```bash
JSON=$(jq -nc \
  --arg title "$TITLE" \
  --arg body  "$BODY" \
  --arg base  "$BASE_BRANCH" \
  --arg head  "$HEAD_BRANCH" \
  --argjson draft  "${DRAFT:-false}" \
  '{title:$title, body:$body, base:$base, head:$head, draft:$draft}')

RESULT=$(gh_post "repos/$GH_REPO/pulls" "$JSON")
PR_NUMBER=$(echo "$RESULT" | sed -nE 's/.*"number":[[:space:]]*([0-9]+).*/\1/p' | head -n1)
PR_URL=$(echo "$RESULT" | sed -nE 's/.*"html_url":[[:space:]]*"([^"]+)".*/\1/p' | head -n1)
echo "Created PR #$PR_NUMBER: $PR_URL"
```

### 5. Optional add-ons (each previewed separately)

If the user mentioned reviewers, labels, or milestones in the same request, apply them as separate operations AFTER the PR exists, each with their own preview/confirm:

```bash
# Reviewers
gh_post "repos/$GH_REPO/pulls/$PR_NUMBER/requested_reviewers" \
  '{"reviewers":["alice","bob"], "team_reviewers":[]}'

# Labels (reuses issues endpoint)
gh_post "repos/$GH_REPO/issues/$PR_NUMBER/labels" \
  '{"labels":["enhancement"]}'
```

## <a name="write-reviewers"></a>Write: request reviewers

Same as above; preview + confirm + POST `.../requested_reviewers`.

## <a name="write-ready"></a>Write: toggle draft state

```bash
# draft -> ready
gh_patch "repos/$GH_REPO/pulls/$N" '{"draft":false}'
# ready -> draft  (GraphQL only; emulate with PATCH not supported)
```

For draft<-ready, fall back to GraphQL via `gh api graphql` if `gh` is available; refuse otherwise and tell the user.

## <a name="write-close"></a>Write: close without merging

```bash
gh_patch "repos/$GH_REPO/pulls/$N" '{"state":"closed"}'
```

---

## Cross-references

- Posting a review on an existing PR -> `skills/code-review/SKILL.md`.
- Checking why CI failed on a PR -> `skills/actions/SKILL.md`.
- Inspecting what's in the PR diff -> `skills/diff/SKILL.md`, using the PR's `head.sha`.

## Failure modes

| Symptom | Cause | Action |
|---|---|---|
| `422 No commits between base and head` | The two branches are identical | Make a commit, then retry |
| `422 A pull request already exists` | Open PR exists for this head | Show that PR's URL instead of creating |
| `403 Resource not accessible by integration` | Token lacks `pull_requests: write` | Re-auth or update PAT scopes |
| Head branch not on origin | Local-only branch | Push it (preview + confirm) |
| Base branch doesn't exist | User specified a wrong base | List branches and ask |

## Self-check

```bash
gh_get "repos/$GH_REPO/pulls?state=open&per_page=1" | head -c 200
```

Non-empty JSON = operational.
