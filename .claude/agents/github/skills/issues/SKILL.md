---
name: issues
description: Lists, searches, and views GitHub issues for this repository. Supports filtering by state, label, or assignee.
---

# Issues Skill

Provides read access to GitHub issues for the repository.

## Commands and what they map to

| User says | Action |
|-----------|--------|
| "list issues", "what issues are open" | List open issues |
| "list all issues", "show closed issues too" | List all issues (open + closed) |
| "issues with label bug" | Filter by label |
| "my issues", "issues assigned to me" | Filter by assignee |
| "view issue #42", "show issue 42" | View a single issue in detail |
| "search issues for X" | Search issues containing keyword X |

## Listing issues

```bash
# Open issues (default)
gh issue list --repo "$REPO" --state open --limit 50

# All issues
gh issue list --repo "$REPO" --state all --limit 50

# By label
gh issue list --repo "$REPO" --label "bug" --state open

# By assignee
gh issue list --repo "$REPO" --assignee "@me" --state open

# Search
gh search issues --repo "$REPO" "KEYWORD" --state open
```

## Viewing a single issue

```bash
gh issue view ISSUE_NUMBER --repo "$REPO"
# Include comments:
gh issue view ISSUE_NUMBER --repo "$REPO" --comments
```

## Output format

When listing issues, present them as a clean table:

```
#    Title                          Labels        State   Created
---  -----------------------------  ------------  ------  ----------
42   Fix AuroraCryptor padding bug  bug           open    2026-04-10
38   Add PostgreSQL JSON DQL func   enhancement   open    2026-03-22
```

When viewing a single issue, show the full body, labels, assignees, and last few comments.

## Additional operations (ask before doing)

If the user asks to create, close, comment on, or assign an issue, confirm first — these are write operations. Then use:

```bash
# Create
gh issue create --repo "$REPO" --title "Title" --body "Body" --label "bug"

# Close
gh issue close ISSUE_NUMBER --repo "$REPO"

# Comment
gh issue comment ISSUE_NUMBER --repo "$REPO" --body "Comment text"
```
