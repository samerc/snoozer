Run the following git commands and produce a clean session summary:

1. `git log --oneline -10` — show recent commits
2. `git diff HEAD~1 --name-status` — show files changed in the last commit
3. `git status --short` — show any uncommitted changes

Then present the output as:

**Last commit:** <hash and message>

**Files changed:**
- Group by: New files / Modified files
- For each file, one line describing what the change does (infer from filename and context)

**Pending actions for the user** (e.g. run migrations, restart server):
- List anything that requires a manual step, based on the changed files (e.g. if a migration .sql file was added, remind the user to run it)

Keep the summary concise — this is an end-of-session handoff, not a diff dump.
