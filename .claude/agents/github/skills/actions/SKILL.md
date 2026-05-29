---
name: actions
description: Reads and interprets GitHub Actions workflows and runs for the current repository. Lists workflows, lists recent runs, drills into a specific run, fetches and summarizes failed-job logs, and proposes the most likely fix. Read-only by design - does not rerun jobs, cancel runs, edit workflow YAML, or change secrets. Use whenever the user mentions "Actions", "workflows", "CI", "the build", "why did X fail", or asks for a status check on recent runs.
---

# Actions Skill

> **STOP - read `github.md` Operating principle #2 first.** Examples below may show `$(...)`, pipes, or `&&` for human readability, but when you execute you MUST translate them to single static commands: `bash .claude/agents/github/bin/gh.sh ...` for any `gh` call, `bash .claude/agents/github/bin/preflight.sh --print KEY` for repo metadata, plain `git ...` for local git. Never use `$(...)` substitution - run separate calls and reuse the literal output.

Read access to GitHub Actions workflows, runs, and job logs, plus interpretation of failures. Intentionally read-only: rerunning jobs and editing workflows are out of scope for this skill.

## Setup

Run the full setup from `skills/setup/SKILL.md`. Reading Actions data needs the API.

## Command map

| User says | Action | Section |
|---|---|---|
| "list workflows", "what workflows exist" | List workflows | [List](#list-workflows) |
| "list runs", "recent runs", "last 10 runs" | List runs | [Runs](#list-runs) |
| "runs for the X workflow" | Filter by workflow | [Runs](#list-runs) |
| "runs on my branch", "runs for main" | Filter by branch | [Runs](#list-runs) |
| "failed runs only" | Filter by conclusion | [Runs](#list-runs) |
| "view run 123456", "show me run X" | Drill into a run | [View](#view-run) |
| "why did run X fail" / "what broke in run X" | Failure interpretation | [Diagnose](#diagnose) |
| "what's the CI status of PR #N" / "of this branch" | Status snapshot | [Status](#status) |

---

## <a name="list-workflows"></a>List workflows

```bash
gh_get "repos/$GH_REPO/actions/workflows" | jq -r '
  .workflows[]
  | [.id, .name, .path, .state] | @tsv
' | column -t -s $'\t'
```

Output as:

```
ID         Name              Path                                          State
---------  ----------------  --------------------------------------------  --------
12345678   PHPUnit Unit      .github/workflows/phpunit-unit.yml            active
12345679   PHPStan           .github/workflows/phpstan.yml                 active
...
```

`state` can be `active`, `disabled_manually`, `disabled_inactivity`. Inactive workflows do not run; surface that fact when relevant.

## <a name="list-runs"></a>List runs

```bash
# Most recent runs across all workflows
gh_get "repos/$GH_REPO/actions/runs?per_page=20"

# Filtered by workflow id or filename
gh_get "repos/$GH_REPO/actions/workflows/phpunit-unit.yml/runs?per_page=20"

# Filtered by branch
gh_get "repos/$GH_REPO/actions/runs?branch=$BRANCH&per_page=20"

# Failed only
gh_get "repos/$GH_REPO/actions/runs?status=failure&per_page=20"
```

Render:

```
Run #              Workflow            Branch          Event       Status     Conclusion   Duration   When                URL
-----------------  ------------------  --------------  ----------  ---------  -----------  ---------  ------------------  ---
12345678901        PHPUnit Unit        main            push        completed  failure      2m04s      2026-05-14 09:12    <html_url>
12345678900        PHPStan             feat/foo        pull_req    completed  success      45s        2026-05-14 09:08    <html_url>
...
```

Default to most recent 10 unless the user asks for more.

## <a name="view-run"></a>View a single run

```bash
RUN_ID="$1"

RUN=$(gh_get "repos/$GH_REPO/actions/runs/$RUN_ID")
JOBS=$(gh_get "repos/$GH_REPO/actions/runs/$RUN_ID/jobs")
```

Render:

```
Run #$RUN_ID  -  <workflow name>
Event:      <event>           Trigger SHA: <head_sha>
Branch:     <branch>          Actor:        @<login>
Status:     <status>          Conclusion:   <conclusion>
Started:    <date>            Duration:     <hh:mm:ss>
URL:        <html_url>

Jobs:
  ✓ test-php-8.5       success     1m43s
  ✗ phpstan            failure     22s     (failed step: "Run PHPStan")
  ↻ deploy             skipped
```

The "failed step" is the first step in that job with `conclusion=failure`. Show it next to the job line.

## <a name="diagnose"></a>Diagnose a failure

This is the high-value path. Goal: turn raw logs into "here's the actual cause and the likely fix".

### 1. Find which job(s) failed

```bash
FAILED_JOB_IDS=$(echo "$JOBS" | jq -r '
  .jobs[] | select(.conclusion=="failure") | .id
')
```

### 2. Pull logs for each failed job

```bash
mkdir -p "/tmp/run-$RUN_ID"
for JOB_ID in $FAILED_JOB_IDS; do
  case "$GH_BACKEND" in
    gh)
      gh api "repos/$GH_REPO/actions/jobs/$JOB_ID/logs" > "/tmp/run-$RUN_ID/job-$JOB_ID.log" ;;
    curl)
      # The logs endpoint returns a 302 redirect to a presigned URL.
      curl -fsSL \
        -H "Authorization: Bearer $GH_TOKEN_EFFECTIVE" \
        -H "Accept: application/vnd.github+json" \
        "$GH_API/repos/$GH_REPO/actions/jobs/$JOB_ID/logs" \
        > "/tmp/run-$RUN_ID/job-$JOB_ID.log" ;;
  esac
done
```

Logs can be many MB. Don't dump them into the chat verbatim.

### 3. Extract the failing region

Two simple heuristics work well together:

```bash
LOG="/tmp/run-$RUN_ID/job-$JOB_ID.log"

# A) Last 200 lines (failure is usually near the end)
tail -n 200 "$LOG"

# B) First occurrence of common failure markers, with 30 lines of context
grep -nE \
  '(Error:|FAIL|FAILED|fatal:|Exception:|Traceback|##\[error\]|Process completed with exit code [^0])' \
  "$LOG" | head -n 5

# C) Show 30 lines around the first '##[error]' line
LN=$(grep -n '##\[error\]' "$LOG" | head -n1 | cut -d: -f1)
if [ -n "$LN" ]; then
  awk -v ln="$LN" 'NR>=ln-30 && NR<=ln+10' "$LOG"
fi
```

### 4. Interpret

Walk the extracted region and identify the first concrete error. Common patterns and what they usually mean:

| Pattern in logs | Likely cause | First thing to try |
|---|---|---|
| `composer require ... requires php ^...` / `not satisfied` | PHP version mismatch | Check `php-version` in workflow vs `composer.json` `require.php` |
| `Class "X" not found` (PHPUnit/PHPStan) | Autoload not regenerated, missing `composer install` step | Add `composer dump-autoload`, or check if a recent rename broke `composer.json` `autoload` paths |
| `SQLSTATE[...]` during tests | DB service not ready / wrong creds | Check the workflow's `services:` block or wait-for-db step |
| `Migration ... not found / already executed` | Drift between branches | Either reset the test DB or pull migrations from main |
| `phpstan / phpcs / cs-fixer ... errors found` | Style/static analysis violation | Show the first 5 errors with file:line |
| `assert*` failure in test output | Real test failure | Show the test name + assertion + file:line |
| `npm ERR! ETARGET` / `version not found` | Bad version in `package.json` | Bump or pin |
| `Permission denied` on a workflow step | `permissions:` block missing the right scope | Suggest the minimal scope needed |
| `Error: Resource not accessible by integration` | GITHUB_TOKEN scope, GHE/Actions permissions | Suggest the scope and a re-run after granting |
| `actions/checkout@v3` or older deprecation warning | Outdated action version | Suggest the latest tag |
| Timeout (`The job running on ...`) | Long-running step beyond `timeout-minutes` | Suggest the step actually causing it; bump timeout only as last resort |
| Flake (passes on rerun without code change) | Networking, test order, race | Flag as flaky; do NOT immediately suggest a code change |

For each failure, output:

```
Job:        <job name>   (id $JOB_ID)
Step:       <step name>  (line $LN in the log)
Conclusion: failure

What happened:
  <verbatim 3-10 lines from the log, the actual error>

Most likely cause:
  <1-2 sentence diagnosis>

Suggested fix:
  - <action 1>
  - <action 2>

Log file (full):
  /tmp/run-$RUN_ID/job-$JOB_ID.log
```

Do not invent fixes. If the log doesn't tell you what failed (truncated, garbled), say so and tell the user where to look:

```
The job's log doesn't surface a clear failure point. Open the run in the browser
to view the full step-by-step output: <html_url>
```

## <a name="status"></a>Status snapshot

For "what's the CI status of this branch / PR #N":

```bash
# For a branch:
SHA=$(git rev-parse "origin/$BRANCH" 2>/dev/null || git rev-parse HEAD)

# For a PR:
SHA=$(gh_get "repos/$GH_REPO/pulls/$N" | sed -nE 's/.*"head":\{[^}]*"sha":[[:space:]]*"([0-9a-f]+)".*/\1/p' | head -n1)

gh_get "repos/$GH_REPO/commits/$SHA/check-runs"
```

Render:

```
SHA: <sha>      Branch: <branch>      <#PR if applicable>

Check                       Status      Conclusion   Duration
--------------------------  ----------  -----------  ---------
PHPUnit Unit                completed   success      1m43s
PHPStan                     completed   failure      22s
PHP-CS-Fixer                queued      -            -
```

Append a one-line verdict:
- "All checks passing." (everything `success`/`skipped`)
- "X checks pending, none failing yet."
- "X failing checks. Want me to dive into them?" (then route to `diagnose`)

## Workflow YAML inspection

If the user wants to *understand* (not edit) a workflow:

```bash
gh_get "repos/$GH_REPO/contents/.github/workflows/<filename>.yml" \
  | jq -r '.content' | base64 -d
```

Read the file locally first when the local checkout has it:

```bash
cat "$(git rev-parse --show-toplevel)/.github/workflows/<filename>.yml"
```

Editing workflow files is intentionally out of scope. If asked, recommend a regular file Edit operation and have the user open a PR.

## Cross-references

- Re-running, watching live, or editing workflows is NOT in this skill - per the project's stated scope. If the user wants any of those, tell them and stop.
- Failures on a PR's head SHA -> use the `code-review` skill's "CI signal" step which calls this skill.

## Failure modes

| Symptom | Cause | Action |
|---|---|---|
| Log endpoint returns 410 Gone | Logs expired (retention) | Tell the user; offer to re-run the workflow (out of scope here) or read the run's summary fields |
| 403 on `/actions/...` | Token lacks `actions: read` (or `workflow` scope on PAT) | Show the scope needed |
| `gh: command not found` while `GH_BACKEND=curl` and `gh api` was hard-coded | Bug in this skill | File a fix against this SKILL.md |
| Workflow has no recent runs | Workflow disabled or event not fired | Confirm `state` and trigger events |

## Self-check

```bash
gh_get "repos/$GH_REPO/actions/runs?per_page=1" | head -c 200
gh_get "repos/$GH_REPO/actions/workflows?per_page=1" | head -c 200
```

Both non-empty = operational.
