---
name: release
description: Creates a new GitHub release. Auto-detects the repo's tag pattern (semver `vX.Y.Z`, branch-versioned `vBRANCH.PATCH`, calendar versioning, or arbitrary), determines the next tag accordingly, generates English release notes from the commit and file history since the previous tag, and publishes via the agent's backend. Includes an optional badge block when the repo carries CI badges under `.github/badges/`. Use whenever the user says "release", "new release", "tag a release", "publish version X", "make a release for branch Y".
---

# Release Skill

> **STOP - read `github.md` Operating principle #2 first.** Examples below may show `$(...)`, pipes, or `&&` for human readability, but when you execute you MUST translate them to single static commands: `bash .claude/agents/github/bin/gh.sh ...` for any `gh` call, `bash .claude/agents/github/bin/preflight.sh --print KEY` for repo metadata, plain `git ...` for local git. Never use `$(...)` substitution - run separate calls and reuse the literal output.

Publishes a versioned GitHub release with auto-generated notes.

## Setup

Run the full setup from `skills/setup/SKILL.md`. Tag creation and release publishing need the API (via `gh release` or REST).

## Step 1 - Parse the user's intent

The user might ask in one of several shapes. Detect which, then collect the rest interactively.

| User says | Shape |
|---|---|
| "release 8.0", "make release for 7.3", "new release on branch 6.1" | Branch-versioned (`v{BRANCH}.{PATCH}`) |
| "release 1.4.2", "tag v1.4.2" | Explicit semver target |
| "new release", "publish a release" | Ambiguous - inspect tag history (Step 2) and infer |
| "new release on main", "tag main as v3.0.0" | Branch + explicit tag |

Store:

```bash
BRANCH=""            # populated when the user named a branch
EXPLICIT_TAG=""      # populated when the user named a full tag
```

## Step 2 - Inspect the tag history

```bash
git fetch --tags --quiet 2>/dev/null || true

# All tags newest first.
mapfile -t ALL_TAGS < <(git tag --sort=-v:refname)
LAST_TAG="${ALL_TAGS[0]:-}"
```

Detect the project's tag scheme:

```bash
SCHEME=""
if [[ "$LAST_TAG" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  SCHEME="semver"
elif [[ "$LAST_TAG" =~ ^v[0-9]+\.[0-9]+$ ]]; then
  SCHEME="major-minor"
elif [[ "$LAST_TAG" =~ ^v[0-9]{4}\.[0-9]{2}\.[0-9]{2}$ ]]; then
  SCHEME="calver-ymd"
elif [[ -z "$LAST_TAG" ]]; then
  SCHEME="none"
else
  SCHEME="custom"     # the user must give us an explicit tag, or confirm a guess
fi
echo "Tag scheme: $SCHEME   Last tag: ${LAST_TAG:-none}"
```

## Step 3 - Decide the next tag

### 3a. If the user passed an explicit tag

Use it. Verify it doesn't already exist locally or remotely:

```bash
NEW_TAG="$EXPLICIT_TAG"
if git rev-parse --verify --quiet "refs/tags/$NEW_TAG" >/dev/null \
   || gh_get "repos/$GH_REPO/git/ref/tags/$NEW_TAG" >/dev/null 2>&1; then
  echo "ERROR: tag $NEW_TAG already exists." >&2
  exit 1
fi
```

### 3b. Branch-versioned (`v{BRANCH}.{PATCH}`) - the original ITP pattern

This is the pattern the user originally wrote this skill for. Keep supporting it.

```bash
LATEST=$(printf "%s\n" "${ALL_TAGS[@]}" \
  | grep -E "^v${BRANCH}\.[0-9]+$" \
  | sort -t. -k1,1V -k2,2n -k3,3n \
  | tail -1)

if [ -z "$LATEST" ]; then
  NEW_TAG="v${BRANCH}.0"
else
  PATCH=$(echo "$LATEST" | grep -oE '[0-9]+$')
  NEW_TAG="v${BRANCH}.$((PATCH + 1))"
fi
```

### 3c. Semver

If the repo follows `vMAJOR.MINOR.PATCH`, ask the user which bump to take (`patch` is the safest default for an autonomous flow):

```bash
LAST="$LAST_TAG"
IFS='.' read -r MAJ MIN PAT <<<"${LAST#v}"
case "${BUMP:-patch}" in
  patch) NEW_TAG="v$MAJ.$MIN.$((PAT+1))" ;;
  minor) NEW_TAG="v$MAJ.$((MIN+1)).0" ;;
  major) NEW_TAG="v$((MAJ+1)).0.0" ;;
esac
```

### 3d. Calendar versioning (`vYYYY.MM.DD`)

```bash
NEW_TAG="v$(date -u +%Y.%m.%d)"
# If that tag already exists today, append .1, .2, ...
if git rev-parse --verify --quiet "refs/tags/$NEW_TAG" >/dev/null; then
  SUFFIX=1
  while git rev-parse --verify --quiet "refs/tags/${NEW_TAG}.${SUFFIX}" >/dev/null; do
    SUFFIX=$((SUFFIX+1))
  done
  NEW_TAG="${NEW_TAG}.${SUFFIX}"
fi
```

### 3e. Custom / unknown

Stop and ask the user to provide the next tag explicitly. Do not guess.

## Step 4 - Resolve the target ref

```bash
TARGET="${BRANCH:-$GH_DEFAULT_BRANCH}"

# Verify the branch exists on the remote.
if ! git ls-remote --exit-code --heads origin "$TARGET" >/dev/null 2>&1; then
  echo "ERROR: branch '$TARGET' does not exist on origin." >&2
  exit 1
fi
```

## Step 5 - Gather change information since the previous tag

Pick the comparison base:

```bash
PREV_TAG=""
case "$SCHEME" in
  major-minor)  # branch-versioned: previous tag is the highest v$BRANCH.*
    PREV_TAG=$(printf "%s\n" "${ALL_TAGS[@]}" \
      | grep -E "^v${BRANCH}\.[0-9]+$" \
      | sort -t. -k1,1V -k2,2n -k3,3n | tail -1) ;;
  semver|calver-ymd|custom)
    PREV_TAG="$LAST_TAG" ;;
  none)
    PREV_TAG="" ;;
esac

if [ -n "$PREV_TAG" ]; then
  COMPARE_JSON=$(gh_get "repos/$GH_REPO/compare/$PREV_TAG...$TARGET")
  COMMITS=$(echo "$COMPARE_JSON" | jq -r '.commits[].commit.message' | sed 's/^/- /')
  FILES=$(echo "$COMPARE_JSON" | jq -r '.files[].filename')
  STATS=$(echo "$COMPARE_JSON" | jq -r '"Commits: \(.total_commits)  Files changed: \(.files | length)"')
else
  # First-ever release: take the last 30 commits.
  COMMITS=$(gh_get "repos/$GH_REPO/commits?sha=$TARGET&per_page=30" \
    | jq -r '.[].commit.message' | sed 's/^/- /')
  FILES="(initial release - no previous tag to compare against)"
  STATS="(initial release)"
fi

echo "=== Stats ===" ; echo "$STATS"
echo "=== Commits ===" ; echo "$COMMITS"
echo "=== Files ===" ; echo "$FILES"
```

## Step 6 - Detect optional decorations

### Badges

```bash
ROOT=$(git rev-parse --show-toplevel)
HAS_BADGES=0
if [ -d "$ROOT/.github/badges" ] && [ -n "$(ls "$ROOT/.github/badges" 2>/dev/null)" ]; then
  HAS_BADGES=1
  mapfile -t BADGES < <(ls "$ROOT/.github/badges")
fi
```

If `HAS_BADGES=1`, build a badge block that references the *new* tag (so the badge URL is stable). Example:

```
[![PHPUnit](https://github.com/$GH_REPO/blob/$NEW_TAG/.github/badges/phpunit-tests.svg?raw=true)](https://github.com/$GH_REPO/actions/workflows/phpunit.yml) ![Coverage](https://github.com/$GH_REPO/blob/$NEW_TAG/.github/badges/coverage.svg?raw=true)
```

Generate one badge link per file in `.github/badges/`. Pair PHPUnit results badge with the workflow URL if a `phpunit*.yml` workflow exists; otherwise emit the bare image.

### Changelog file

```bash
CHANGELOG=""
for cand in "$ROOT/CHANGELOG.md" "$ROOT/CHANGES.md" "$ROOT/HISTORY.md"; do
  if [ -f "$cand" ]; then CHANGELOG="$cand"; break; fi
done
```

If a changelog exists, the user can opt to use the matching section as the release notes instead of generating one. Offer it explicitly.

## Step 7 - Write the release notes

Draft the body in English with this structure:

```
{BADGE_BLOCK if HAS_BADGES}

## What's Changed

{your written summary — see rules below}

**Full Changelog**: https://github.com/$GH_REPO/compare/$PREV_TAG...$NEW_TAG
```

Rules for the summary:

- Open with a 1-2 sentence headline that names the most user-visible change.
- Group related changes into sub-sections (`### Bug fixes`, `### Features`, `### Dependency updates`, `### Internal`).
- One short paragraph per group; describe what changed and *why*, not "file X was edited".
- Avoid filler ("various improvements", "miscellaneous"). If a commit doesn't have a clear user-facing impact, list it under `### Internal`.
- If the project has an existing release note style (look at the previous release with `gh_get "repos/$GH_REPO/releases/tags/$PREV_TAG"`), mirror it.

For the first release (no `PREV_TAG`), replace the Full Changelog line with:

```
**Full Changelog**: https://github.com/$GH_REPO/commits/$TARGET
```

## Step 8 - Preview and confirm

```
--- PREVIEW ---
Action: Create GitHub release in $GH_REPO
Tag:    $NEW_TAG
Target: $TARGET   (branch)
Prev:   ${PREV_TAG:-(none — first release)}
Latest: yes
Draft:  no

Body:
<RELEASE_BODY>
--- END PREVIEW ---

Proceed? (yes/no)
```

## Step 9 - Create the release

Prefer the `gh` CLI when available - it handles long bodies cleanly via `--notes-file`:

```bash
TMPFILE=$(mktemp)
printf '%s\n' "$RELEASE_BODY" > "$TMPFILE"

case "$GH_BACKEND" in
  gh)
    gh release create "$NEW_TAG" \
      --repo "$GH_REPO" \
      --title "$NEW_TAG" \
      --target "$TARGET" \
      --notes-file "$TMPFILE" \
      --latest
    ;;
  curl)
    JSON=$(jq -nc \
      --arg tag  "$NEW_TAG" \
      --arg tgt  "$TARGET" \
      --arg name "$NEW_TAG" \
      --rawfile body "$TMPFILE" \
      '{tag_name:$tag, target_commitish:$tgt, name:$name, body:$body, draft:false, prerelease:false, make_latest:"true"}')
    gh_post "repos/$GH_REPO/releases" "$JSON"
    ;;
esac

rm -f "$TMPFILE"
```

## Step 10 - Confirm

Print the URL returned by the API (`html_url` field, or the URL emitted by `gh release create`). End with a single-sentence confirmation.

## Common edge cases

| Situation | Action |
|---|---|
| Tag already exists | Stop. Tell the user the conflict and the next free tag. |
| `gh` not authenticated, no `GH_TOKEN` | Setup will have set `GH_BACKEND=git-only` - refuse and explain. |
| `gh release create` rejects because `target` is not pushed | Push the target branch first (preview + confirm), then retry. |
| Zero commits since `PREV_TAG` | Warn the user and ask whether they really want an empty release (e.g., for tagging an artifact rebuild). |
| The repo has no tags AND no `default_branch` | Setup will have fallback `main` - confirm before proceeding. |
| User wants a pre-release | Pass `--prerelease` to `gh release create`, or `prerelease:true` in the REST JSON. Preview must mention "pre-release: yes". |

## Self-check

```bash
gh_get "repos/$GH_REPO/releases?per_page=1" | head -c 200
git tag --sort=-v:refname | head -n3
```

API reachable and tags listable = operational.
