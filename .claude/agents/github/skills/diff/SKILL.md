---
name: diff
description: Compares two refs (branches, tags, releases, commits) and reports what changed. Default output is a readable summary (file stats, commit list); full unified diff is produced only on explicit request or for a specific file. Large diffs are saved to /tmp and referenced by path so the chat stays usable. Use whenever the user asks to compare, diff, or contrast two refs in any combination.
---

# Diff Skill

> **STOP - read `github.md` Operating principle #2 first.** Examples below may show `$(...)`, pipes, or `&&` for human readability, but when you execute you MUST translate them to single static commands: `bash .claude/agents/github/bin/gh.sh ...` for any `gh` call, `bash .claude/agents/github/bin/preflight.sh --print KEY` for repo metadata, plain `git ...` for local git. Never use `$(...)` substitution - run separate calls and reuse the literal output.

Resolves the user's request into two refs and produces a comparison.

## Setup

Pure-git operations work without network access. Use the minimum probe:

```bash
git rev-parse --is-inside-work-tree >/dev/null || { echo "Not a git repo." >&2; exit 1; }
```

Only call the full setup from `skills/setup/SKILL.md` if the user asked for something that needs the API (e.g., "diff between latest release and main" when the release tag is not yet fetched locally).

## Step 1 - Parse the two refs from the user's request

| User says | Ref A | Ref B |
|---|---|---|
| "diff main and feature/x" | `main` | `feature/x` |
| "what changed between v8.0.5 and v8.0.7" | `v8.0.5` | `v8.0.7` |
| "diff between the last two releases" | second-most-recent tag | latest tag |
| "what's in this branch that's not in main" | `main` | current branch (`HEAD`) |
| "what's in main that's not in my branch" | current branch | `main` |
| "diff HEAD~1 HEAD" | `HEAD~1` | `HEAD` |
| "diff src/Foo.php between main and develop" | `main` | `develop` (single file) |

Store as `REF_A` and `REF_B`. Ref B is the "newer" / "current" side; the diff is read as `REF_A...REF_B`.

For "the last two releases":

```bash
mapfile -t TAGS < <(git tag --sort=-v:refname | head -n2)
REF_B="${TAGS[0]}"
REF_A="${TAGS[1]}"
```

If the user names a tag that doesn't exist locally, fetch:

```bash
git fetch --tags --quiet
```

## Step 2 - Make sure both refs exist

```bash
for r in "$REF_A" "$REF_B"; do
  if ! git rev-parse --verify --quiet "$r^{}" >/dev/null; then
    echo "ERROR: ref '$r' does not exist locally. Try 'git fetch --all --tags'." >&2
    exit 1
  fi
done
```

## Step 3 - Produce the summary (default output)

This is the default whenever the user does not explicitly ask for a full diff.

```bash
echo "=== Comparing ${REF_A}...${REF_B} ==="
echo
echo "-- Commit count --"
git rev-list --count "${REF_A}..${REF_B}"

echo
echo "-- File stats --"
git diff --stat "${REF_A}...${REF_B}"

echo
echo "-- Commits --"
git log --oneline --no-merges "${REF_A}..${REF_B}"
```

### Interpretation rules

- If `--stat` shows more than 30 files OR `--shortstat` reports more than 1000 changed lines, label this a "large diff" and follow Step 5 before offering the full unified diff.
- If the commit list shows merge commits and the user asked "what changed", include `git log --oneline "${REF_A}..${REF_B}"` (without `--no-merges`) as well so they see the merge structure.

## Step 4 - Full diff (on request, or for a specific file)

If the user explicitly asks ("show the full diff", "what does it look like line by line") OR scopes to a path:

```bash
# Full diff:
git diff "${REF_A}...${REF_B}"

# Single file:
git diff "${REF_A}...${REF_B}" -- "<path>"

# Specific directory:
git diff "${REF_A}...${REF_B}" -- "src/SomeDir/"
```

Use `...` (three dots, "diff the tip of B against the merge-base") rather than `..` (two dots, "raw range"). Three-dot semantics are what users mean by "what's the difference between these branches".

## Step 5 - Large-diff handling

When the summary shows more than 30 files or more than 1000 changed lines:

```bash
OUT="/tmp/diff-$(echo "${REF_A}" | tr '/' '_')-$(echo "${REF_B}" | tr '/' '_').patch"
git diff "${REF_A}...${REF_B}" > "$OUT"
echo "Full diff saved to: $OUT  ($(wc -l < "$OUT") lines)"
echo "Inspect a slice with: head -200 \"$OUT\"  /  grep -n 'pattern' \"$OUT\""
```

Then ask the user which file(s) or directories they want to see inline.

## Step 6 - Useful one-liners the user may ask for explicitly

| Request | Command |
|---|---|
| "Which files were renamed?" | `git diff --diff-filter=R --summary "${REF_A}...${REF_B}"` |
| "Which files were deleted?" | `git diff --name-only --diff-filter=D "${REF_A}...${REF_B}"` |
| "Which files were added?" | `git diff --name-only --diff-filter=A "${REF_A}...${REF_B}"` |
| "Who touched these files?" | `git shortlog -sne "${REF_A}..${REF_B}"` |
| "Just the file list" | `git diff --name-only "${REF_A}...${REF_B}"` |
| "When did each ref point here?" | `git show -s --format='%h %ci %s' "${REF_A}" "${REF_B}"` |

## Step 7 - Output framing

Present the result with this structure:

```
Comparing: <REF_A> -> <REF_B>
Commits:   <N>
Files:     <added>+, <modified>~, <deleted>-, <renamed>R   (total <N>)
Lines:     +<added> / -<deleted>

Top changes (by line count):
  <file>   +N -N
  ...

Commits:
  <sha> <subject>
  ...
```

After the table, write 1-3 sentences summarizing the *meaning* of the diff in plain English (what changed and why, inferred from commit messages and changed paths). This is what makes the agent more useful than a raw `git diff`.

## Step 8 - When the diff spans a fork or untracked remote

If the user asks "diff between PR #N's head and main", route through the `pulls` skill first to resolve the PR's head ref:

```bash
# After setup is loaded so gh_get is available:
HEAD_SHA=$(gh_get "repos/$GH_REPO/pulls/$N" | sed -nE 's/.*"sha":[[:space:]]*"([0-9a-f]+)".*/\1/p' | head -n1)
git fetch origin "$HEAD_SHA" --quiet
REF_B="$HEAD_SHA"
REF_A="$GH_DEFAULT_BRANCH"
```

## Failure modes

| Symptom | Cause | Action |
|---|---|---|
| `unknown revision or path not in working tree` | Local repo doesn't have the ref | `git fetch --all --tags`, retry |
| Diff is empty when user expected changes | Wrong direction (`A...B` vs `B...A`) | Re-confirm which ref is "before" and which is "after" |
| Merge base far in the past, diff huge | Long-lived branch | Suggest `git merge-base A B` and ask whether they want diff vs merge-base (`A...B`) or full range (`A..B`) |
| `fatal: ambiguous argument` | Ref name collides with a path | Use `--` separator: `git diff A...B -- <path>` |

## Self-check

```bash
git rev-parse --verify HEAD >/dev/null && \
git diff --stat HEAD~1...HEAD | head -5
```

If this prints stats for the last commit, the skill is operational.
