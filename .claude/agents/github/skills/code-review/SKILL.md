---
name: code-review
description: Performs a structured code review of an existing pull request. Reads the diff, the commit history, the CI status, the existing comments and reviews, and (when present) the project's own rules in CLAUDE.md and .claude/rules/*. Produces a written review covering correctness, security, test coverage, style, and breaking-change risks - tailored to the repo's primary language. Can also post the review on GitHub (Approve / RequestChanges / Comment, with line-level inline comments) but only after explicit confirmation. Use whenever the user says "review PR", "code review", "what's wrong with PR #N", "approve / request changes on PR".
---

# Code Review Skill

> **STOP - read `github.md` Operating principle #2 first.** Examples below may show `$(...)`, pipes, or `&&` for human readability, but when you execute you MUST translate them to single static commands: `bash .claude/agents/github/bin/gh.sh ...` for any `gh` call, `bash .claude/agents/github/bin/preflight.sh --print KEY` for repo metadata, plain `git ...` for local git. Never use `$(...)` substitution - run separate calls and reuse the literal output.

Performs a structured, opinionated review of a pull request. The default outcome is a written report in chat. Posting that report on GitHub is a separate, explicit step.

## Setup

Run the full setup from `skills/setup/SKILL.md`. This skill needs the API for diffs/comments and may need it again for posting.

## Inputs

The user must (or should be asked to) provide the PR number. If they say "review the PR I just opened", reuse the last PR number from the conversation. If they say "review my branch", fall back to the `pulls` skill's diff logic against `$GH_DEFAULT_BRANCH` and produce the same report without a PR number (note in the report that it isn't posted anywhere).

## Step 1 - Gather the facts (read-only)

```bash
N="$1"   # PR number

PR=$(gh_get "repos/$GH_REPO/pulls/$N")
HEAD_SHA=$(echo "$PR" | sed -nE 's/.*"head":\{[^}]*"sha":[[:space:]]*"([0-9a-f]+)".*/\1/p' | head -n1)
BASE_SHA=$(echo "$PR" | sed -nE 's/.*"base":\{[^}]*"sha":[[:space:]]*"([0-9a-f]+)".*/\1/p' | head -n1)

# Files changed (paginated; loop if more than 100)
FILES_JSON=$(gh_get "repos/$GH_REPO/pulls/$N/files?per_page=100")

# Commits
COMMITS=$(gh_get "repos/$GH_REPO/pulls/$N/commits?per_page=100")

# Existing reviews and comments
REVIEWS=$(gh_get "repos/$GH_REPO/pulls/$N/reviews")
INLINE_COMMENTS=$(gh_get "repos/$GH_REPO/pulls/$N/comments")
ISSUE_COMMENTS=$(gh_get "repos/$GH_REPO/issues/$N/comments")

# CI runs against the head sha
CHECKS=$(gh_get "repos/$GH_REPO/commits/$HEAD_SHA/check-runs")
```

For the actual diff, prefer the local repo (cheaper, full unified diff available):

```bash
git fetch origin "+refs/pull/$N/head:refs/pull/$N/head" --quiet
git diff "$BASE_SHA...refs/pull/$N/head" > "/tmp/pr-$N.diff"
```

If the local fetch fails (no access to remote / shallow clone), fall back to the REST diff:

```bash
case "$GH_BACKEND" in
  gh)   gh api "repos/$GH_REPO/pulls/$N" -H "Accept: application/vnd.github.v3.diff" > "/tmp/pr-$N.diff" ;;
  curl) curl -fsS \
          -H "Authorization: Bearer $GH_TOKEN_EFFECTIVE" \
          -H "Accept: application/vnd.github.v3.diff" \
          "$GH_API/repos/$GH_REPO/pulls/$N" > "/tmp/pr-$N.diff" ;;
esac
```

## Step 2 - Detect the repo's language and conventions

```bash
ROOT=$(git rev-parse --show-toplevel)

# Primary language by file count among changed files (cheap heuristic).
PRIMARY_LANG=$(jq -r '.[].filename' <<<"$FILES_JSON" \
  | grep -oE '\.[a-zA-Z]+$' \
  | sort | uniq -c | sort -rn | awk 'NR==1{print $2}')
```

Map the extension to a language profile:

| Ext | Language | Extra checks |
|---|---|---|
| `.php` | PHP | PSR-12, strict types, Doctrine N+1, deprecated APIs, `dirname(__FILE__)` |
| `.ts` / `.tsx` | TypeScript | `any`, missing `strict` types, React hook rules |
| `.js` / `.jsx` | JavaScript | Same as TS minus type checks; ESM/CJS mix |
| `.py` | Python | Type hints, `assert` in prod code, mutable default args |
| `.go` | Go | Error handling, goroutine leaks, context propagation |
| `.rs` | Rust | `unwrap()` in lib code, lifetime smells, `unsafe` blocks |
| `.sql` | SQL / migrations | Destructive ops, missing indexes, `NOT NULL` on existing tables, blocking locks |

Read any of these if present:

- `$ROOT/CLAUDE.md`
- `$ROOT/.claude/CLAUDE.md`
- All files under `$ROOT/.claude/rules/`
- `$ROOT/CONTRIBUTING.md`
- `$ROOT/.editorconfig`

Surface anything they say about style, security, testing, or commit format. Treat their rules as mandatory checklist items in the review.

## Step 3 - Review checklist

Walk through the diff once, taking notes per category. Cite specific files and line numbers. Don't repeat what's already in another reviewer's comment unless you disagree.

### 3a. Correctness
- Does each function do what its name says?
- Are edge cases handled (empty input, null, error paths)?
- Off-by-one in loops, indexing, slice bounds.
- Race conditions / concurrency bugs.
- Resource cleanup (file handles, DB transactions, locks).
- For bug fixes: is there a regression test?

### 3b. Security
- Untrusted input reaching `eval` / `system` / dynamic SQL / shell.
- AuthN / AuthZ: does new code skip an existing check?
- Secrets in commits, logs, or comments.
- SSRF, SQLi, XSS for web code (extension-dependent).
- Crypto: own implementation, weak primitives (MD5/SHA1 for security), missing IV/nonce.

### 3c. Tests
- Coverage of new code (especially error paths).
- Tests of the right kind (unit vs integration vs functional - if the project has groups, e.g. PHPUnit's `#[Group(...)]`, check they're tagged correctly).
- Tests that actually assert something (no `$this->assertTrue(true)`).
- Flaky patterns: time, randomness, network, file system races.

### 3d. Style and conventions
- Matches the project's style rules (read in Step 2).
- Public API additions are documented.
- Dead code, debug `print`/`var_dump`/`console.log`, commented-out code.
- Naming: function/class/variable conventions match the rest of the file.

### 3e. Architecture / scope
- Is the PR doing what its title says, or has scope creep snuck in?
- Are abstractions justified by 3+ uses, or speculative?
- Are there layering violations (e.g., a controller reaching into a repository class directly)?

### 3f. Breaking changes / migration safety
- Public API removed or renamed without a deprecation path.
- DB migrations that block writes or fail on big tables.
- Config changes that need a deploy-side action.

### 3g. CI signal
- Any failing checks? Read the failure logs (delegate to `skills/actions/SKILL.md`'s helpers).
- Are failures from the PR itself or from a flaky / unrelated job?

## Step 4 - Produce the report

Format the chat output as:

```
# Review of #<N>: <title>

**Verdict:** APPROVE | REQUEST_CHANGES | COMMENT
**Summary:** 1-2 sentences explaining the verdict.

## Blockers (must fix before merge)
- <file:line>: <issue> -> <suggested change>
- ...

## Suggestions (nice to have)
- <file:line>: <issue> -> <suggested change>
- ...

## Nits (style / polish)
- <file:line>: <issue>
- ...

## Tests
- <coverage observations, missing cases>

## CI status
- <summary of passing/failing jobs, link to failing run>

## Notes for the author
- <anything contextual: prior discussion, related PRs, things you couldn't fully verify>
```

Verdict rules:
- `REQUEST_CHANGES` if there is at least one Blocker.
- `APPROVE` if no Blockers and no failing CI directly caused by the diff.
- `COMMENT` otherwise (failing CI you can't attribute, or you want a second opinion).

## Step 5 - Offer to post the review

Stop here unless the user explicitly asks to post. When they do:

```
--- PREVIEW ---
Action: Submit review on PR #<N> in $GH_REPO
Verdict: <APPROVE | REQUEST_CHANGES | COMMENT>
Summary body length: <N> chars
Inline comments: <K>
--- END PREVIEW ---

Proceed? (yes/no)
```

On `yes`:

```bash
# Build inline comments. For each, you need path + line (RIGHT side) or start_line+line for multi-line.
# Inline comment objects look like:
#   {"path":"src/Foo.php","line":42,"side":"RIGHT","body":"..."}

REVIEW_BODY=$(cat <<'EOF'
<the rendered "Summary" + "Blockers" + "Suggestions" + "Tests" + "CI status" sections>
EOF
)

# event values: APPROVE | REQUEST_CHANGES | COMMENT
JSON=$(jq -nc \
  --arg body "$REVIEW_BODY" \
  --arg evt  "REQUEST_CHANGES" \
  --argjson comments "$INLINE_COMMENTS_ARRAY" \
  '{body:$body, event:$evt, comments:$comments}')

RESULT=$(gh_post "repos/$GH_REPO/pulls/$N/reviews" "$JSON")
echo "$RESULT" | sed -nE 's/.*"html_url":[[:space:]]*"([^"]+)".*/Submitted: \1/p' | head -n1
```

### Posting only a summary (no inline comments)

Same JSON without the `comments` array:

```bash
JSON=$(jq -nc --arg body "$REVIEW_BODY" --arg evt "COMMENT" '{body:$body, event:$evt}')
gh_post "repos/$GH_REPO/pulls/$N/reviews" "$JSON"
```

### Approve + merge (only when the user explicitly asks "approve and merge")

Two preview/confirm gates: one for the approving review, a second for the merge.

```bash
# 1) Approve
JSON=$(jq -nc --arg body "$REVIEW_BODY" '{body:$body, event:"APPROVE"}')
gh_post "repos/$GH_REPO/pulls/$N/reviews" "$JSON"

# 2) Merge
#    merge_method: merge | squash | rebase (default: merge; the project may pin a preference)
MERGE_METHOD="${MERGE_METHOD:-squash}"
gh_post "repos/$GH_REPO/pulls/$N/merge" "$(jq -nc --arg m "$MERGE_METHOD" '{merge_method:$m}')"
```

Refuse to merge if any required CI check is failing. Tell the user which.

## Step 6 - After posting

Print the review URL. If the verdict was REQUEST_CHANGES, also remind the author what's needed to flip to APPROVE.

## Patterns that come up in real reviews

- **"The diff is huge; I can't review it all in one pass."** Group files by directory or by concern, summarize each cluster, then deep-dive only the risky clusters.
- **"There's an unrelated change."** Flag it explicitly under Blockers as "out of scope" with a request to split into a separate PR.
- **"I think this is fine but I'm not sure about X."** Use the COMMENT verdict and ask a question instead of guessing.
- **"The tests pass but I don't trust them."** Look at coverage of the changed paths, not just overall coverage. Call out untested edge cases.

## Failure modes

| Symptom | Cause | Action |
|---|---|---|
| Diff fetch fails locally | Shallow clone | `git fetch --unshallow` or use REST diff |
| `422 Unprocessable Entity` on review | Bad `line` numbers (lines must exist on the new file) | Recompute line numbers from the diff hunks |
| `403 Review cannot be requested from pull request author` | Trying to assign self as reviewer | Skip yourself in the reviewer list |
| Inline comments not appearing | Used `commit_id` from base instead of HEAD | Always reference `head.sha` |

## Self-check

```bash
N=$(gh_get "repos/$GH_REPO/pulls?state=open&per_page=1" | sed -nE 's/.*"number":[[:space:]]*([0-9]+).*/\1/p' | head -n1)
[ -n "$N" ] && gh_get "repos/$GH_REPO/pulls/$N/files?per_page=1" | head -c 200
```

If a PR exists and its files list is reachable, the skill is operational.
