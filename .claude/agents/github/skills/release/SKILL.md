---
name: release
description: Creates a new GitHub release for a specified branch. Determines the next version tag automatically, generates English release notes from the commit/diff history, and publishes via gh CLI.
---

# Release Skill

Creates a versioned GitHub release for a given branch, auto-incrementing the patch number.

## Step 1 — Parse the branch

Extract the branch from the user's input:

| User says | Branch |
|-----------|--------|
| "release 8.0" | `8.0` |
| "make release for 7.3" | `7.3` |
| "new release on branch 6.1" | `6.1` |

Store it as `BRANCH` (e.g., `BRANCH=8.0`).

## Step 2 — Find the latest existing release for this branch

```bash
LATEST_TAG=$(gh release list --repo "$REPO" --json tagName --jq '.[].tagName' \
  | grep -E "^v${BRANCH}\.[0-9]+$" \
  | sort -t. -k1,1V -k2,2n -k3,3n \
  | tail -1)

echo "Latest tag for v${BRANCH}.*: ${LATEST_TAG:-none}"
```

## Step 3 — Determine next version

- If `LATEST_TAG` is empty → `NEW_TAG="v${BRANCH}.0"`
- If `LATEST_TAG` is `v8.0.7` → extract patch `7`, increment to `8` → `NEW_TAG="v${BRANCH}.8"`

```bash
if [ -z "$LATEST_TAG" ]; then
  NEW_TAG="v${BRANCH}.0"
else
  PATCH=$(echo "$LATEST_TAG" | grep -oE '[0-9]+$')
  NEXT_PATCH=$((PATCH + 1))
  NEW_TAG="v${BRANCH}.${NEXT_PATCH}"
fi
echo "New tag: $NEW_TAG"
```

## Step 4 — Gather change information

Use the GitHub API to get commits and changed files since the last release.

**If there is a previous release:**
```bash
COMMITS=$(gh api "repos/${REPO}/compare/${LATEST_TAG}...${BRANCH}" --jq '[.commits[].commit.message] | join("\n")')
FILES=$(gh api "repos/${REPO}/compare/${LATEST_TAG}...${BRANCH}" --jq '[.files[].filename] | join("\n")')
STATS=$(gh api "repos/${REPO}/compare/${LATEST_TAG}...${BRANCH}" --jq '"Commits: \(.total_commits), Files changed: \(.files | length)"')
echo "=== Stats ===" && echo "$STATS"
echo "=== Commits ===" && echo "$COMMITS"
echo "=== Files ===" && echo "$FILES"
```

**If this is the first release (no `LATEST_TAG`):**
```bash
COMMITS=$(gh api "repos/${REPO}/commits?sha=${BRANCH}&per_page=30" --jq '[.[].commit.message] | join("\n")')
FILES="(initial release — no previous tag to compare against)"
```

## Step 5 — Check for PHPUnit badges

```bash
HAS_BADGES=$(gh api "repos/${REPO}/contents/.github/badges" --jq '.[].name' 2>/dev/null | grep -c "phpunit" || echo 0)
```

If `HAS_BADGES > 0`, include the badge block (see Step 7). Otherwise omit it.

## Step 6 — Write the release notes

Using the commits and files gathered above, write release notes in **English** that:

- Have a `## What's Changed` section
- Describe the actual changes meaningfully — not just "updated files", but what was fixed, added, or improved and why
- Group related changes under sub-headings if there are multiple distinct areas (e.g., `### Bug fix: …`, `### New feature: …`, `### Dependency update: …`)
- Are written for a developer reading the GitHub releases page
- Are concise — one short paragraph per change group is enough

**Reference the existing note style:** look at the v8.0.7 release for tone and level of detail. Avoid generic filler like "various improvements"; be specific about what changed.

## Step 7 — Assemble the full release body

```
BADGE_BLOCK (if HAS_BADGES)
## What's Changed

{your written summary}

**Full Changelog**: https://github.com/{REPO}/compare/{LATEST_TAG}...{NEW_TAG}
```

**Badge block template** (substitute `{NEW_TAG}` and `{REPO}`):
```
[![PHPUnit](https://github.com/{REPO}/blob/{NEW_TAG}/.github/badges/phpunit-tests.svg?raw=true)](https://github.com/{REPO}/actions/workflows/phpunit.yml) ![PHPUnitTests](https://github.com/{REPO}/blob/{NEW_TAG}/.github/badges/phpunit.svg?raw=true) ![PHPUnitCoverage](https://github.com/{REPO}/blob/{NEW_TAG}/.github/badges/coverage.svg?raw=true) ![PHPUnitStatements](https://github.com/{REPO}/blob/{NEW_TAG}/.github/badges/statements.svg?raw=true)
```

For the **first release** (no `LATEST_TAG`), use this instead:
```
**Full Changelog**: https://github.com/{REPO}/commits/{BRANCH}
```

## Step 8 — Show the user before creating

Print the following for the user to review:

```
Tag:    {NEW_TAG}
Branch: {BRANCH}
Repo:   {REPO}

--- RELEASE NOTES PREVIEW ---
{full release body}
-----------------------------

Proceed with creating this release? (yes/no)
```

Wait for explicit confirmation before proceeding.

## Step 9 — Create the release

```bash
gh release create "$NEW_TAG" \
  --repo "$REPO" \
  --title "$NEW_TAG" \
  --target "$BRANCH" \
  --notes "$RELEASE_BODY" \
  --latest
```

Use `--notes-file` if the body is too long for an inline argument:
```bash
TMPFILE=$(mktemp)
echo "$RELEASE_BODY" > "$TMPFILE"
gh release create "$NEW_TAG" \
  --repo "$REPO" \
  --title "$NEW_TAG" \
  --target "$BRANCH" \
  --notes-file "$TMPFILE" \
  --latest
rm "$TMPFILE"
```

## Step 10 — Confirm

Print the release URL returned by `gh release create` and confirm success to the user.

## Error handling

| Situation | Action |
|-----------|--------|
| Tag already exists | Stop immediately, report the conflict, suggest the correct next tag |
| `gh` auth fails | Ask user to run `gh auth login` |
| Branch does not exist on remote | Warn and abort |
| Zero commits since last release | Warn the user and ask if they still want to proceed |
