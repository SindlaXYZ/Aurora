---
name: setup
description: Probes the local environment for git, gh, GitHub authentication, and repo metadata. Resolves the effective "backend" (gh CLI, REST API via curl, or git-only) and exports a set of environment variables that every other skill in this agent relies on. Use whenever a skill needs to talk to GitHub, or when the user explicitly asks "is github / gh / git working".
---

# Setup Skill

Resolves how this agent will talk to GitHub for the current request and the current repository. Every other skill calls into this one first.

## How to run setup

**Do not write bash preflight yourself.** All of the logic that previous versions of this skill expanded inline (parsing git remote URLs, detecting backends, validating tokens, computing API base, finding default branch) lives inside the helper at `bin/preflight.sh`. The agent calls that helper with static arguments. See `github.md` for the "no dynamic shell syntax" rule.

### Full check (when the user asks "is github working" or you want a summary)

```bash
bash .claude/agents/github/bin/preflight.sh
```

On success the helper prints (to stdout):

```
======= GitHub setup =======
Repo:           owner/repo
Host:           github.com
API:            https://api.github.com
Default branch: main
Backend:        gh
============================
```

Read those values from the tool result and use them as literals in any subsequent command. Backend values:

| Backend | Meaning |
|---|---|
| `gh` | `gh` CLI is installed and authenticated for the resolved host - use `bash bin/gh.sh <args>` for everything. |
| `gh-needs-auth` | `gh` is installed but not authenticated for the resolved host - the token from `.credentials.local` is still used by `bin/gh.sh` (it exports `GH_TOKEN`), so this state is functionally equivalent to `gh` for API calls. |
| `curl` | `gh` is NOT installed. Any non-`api` `gh` subcommand will fail. For raw REST you can still use `bash bin/gh.sh api <path>` if the user installs `gh`, or fall back to direct `curl` against `<API>` with `Authorization: Bearer <token>` (but never let the token leak into your output). |

On failure the helper exits 2 and writes a recovery block to stderr. Relay that stderr verbatim to the user. Do NOT try to recover by running bash yourself - the recovery instructions in the helper output already cover all common failure modes.

### Single-value lookup (when you need one literal in a follow-up command)

```bash
bash .claude/agents/github/bin/preflight.sh --print REPO
bash .claude/agents/github/bin/preflight.sh --print HOST
bash .claude/agents/github/bin/preflight.sh --print API
bash .claude/agents/github/bin/preflight.sh --print DEFAULT_BRANCH
bash .claude/agents/github/bin/preflight.sh --print BACKEND
```

Each prints one value to stdout. Read it from the tool result and substitute it as a literal in the next static command - do NOT use `$(bash bin/preflight.sh --print REPO)` style substitution. That triggers the "shell syntax cannot be statically analyzed" prompt.

### Self-check (when the user asks "is github working")

1. Run `bash .claude/agents/github/bin/preflight.sh`.
2. If it exits 0 with a summary, report: `GitHub access is fully operational.` plus the resolved repo, backend, and default branch from the summary.
3. If it exits 2, relay the helper's stderr verbatim and stop.

## What other skills consume from this skill

Conceptually (these are values, not exported shell variables - they live in your reasoning context after you read the preflight output):

| Value | Source | Used by |
|---|---|---|
| `REPO` (owner/repo) | `--print REPO` | API paths in every skill |
| `HOST` (github.com or GHE host) | `--print HOST` | URL construction |
| `API` (https://api.github.com or https://host/api/v3) | `--print API` | Direct `curl` fallback if ever needed |
| `DEFAULT_BRANCH` | `--print DEFAULT_BRANCH` | `diff`, `pulls`, `release` use it as the base ref |
| `BACKEND` | `--print BACKEND` | Decide whether `bash bin/gh.sh non-api` calls will work |

## Failure-mode shortcuts

Most failures are handled by the helper itself with detailed recovery text. The mapping below is for understanding only - you should still relay the helper's stderr verbatim rather than rephrasing it.

| Symptom | Cause | What the helper tells the user |
|---|---|---|
| `!!! CREDENTIAL TEMPLATE COMPROMISED !!!` | `.credentials` modified | Restore the sentinel line; if a real token was written there, revoke it. |
| `$CRED_LOCAL does not exist` | First-time setup | Step-by-step PAT creation flow + the exact `echo -n "GH_TOKEN=..." > ...` line. |
| `GH_TOKEN is empty in $CRED_LOCAL` | File present but token field blank | Add the value after `GH_TOKEN=`. |
| `GH_TOKEN failed authentication (HTTP 401/403)` | Bad / expired / under-scoped token | Regenerate the token and rewrite the local file. |
| `Not inside a git working tree` | Wrong CWD | Ask the user which path they meant. |
| `No git remote configured` | Fresh repo without a remote | Suggest `git remote add origin <url>`. |
| `Could not parse repo slug` | Remote points somewhere this parser does not handle (e.g. GitLab) | Stop. This agent is GitHub-only. |
