---
name: commit-and-push
description: Lint, auto-fix, commit, and push the local changes in this repo to origin. Use whenever the user asks to commit, ship, save, or push their work here — prefer this over a bare `git commit`/`git push`, since the pre-flight Pint/ESLint check is the reason this skill exists. Trigger on phrases like "commit this", "commit and push", "push these changes", "ship it".
---

# Commit and Push

Commits and pushes the current changes, but only after the project's linters have run and passed — that ordering is the entire point of this skill over a plain `git commit`.

## Steps

1. **Check for changes.** Run `git status --short`. If there's nothing to commit, say so and stop — don't invent work to do.

2. **Lint and auto-fix.** Run `make lint-fix` from the repo root (Pint for `backend/`, `eslint --fix` for `frontend/`). This modifies files in place; that's expected.

3. **Verify clean.** Run `make lint`. If it still fails — meaning Pint or ESLint hit something it can't auto-fix — STOP. Show the user the failing output and do not commit. Don't hand-patch lint errors yourself unless the user asks you to; that's a separate task from "commit my changes."

4. **Stage deliberately.** `git add` the specific files that changed — never `git add -A` or `git add .`. This includes both the user's original edits and anything step 2's auto-fix touched. If anything staged looks like a secret (`.env`, credentials, keys) even if it was modified, exclude it and flag it to the user instead of silently committing it.

5. **Ask for an optional ticket number.** Before drafting the message, ask the user if this change is tied to a ticket/issue number (e.g. "Any ticket number for this, or should I skip it?"). It's optional — if they skip it or say no, move on without pushing further. If they give one, prefix the commit subject with it in brackets, e.g. `[ABC-123] fix Sanctum stateful check missing Referer header`.

6. **Write the commit message.** Look at `git diff --cached` and `git log --oneline -10` to match this repo's existing style. One line, imperative mood, focused on *why* the change matters rather than restating the diff — e.g. "fix Sanctum stateful check missing Referer header in tests", not "changed 3 files" or "updates". Add a short body only when the reasoning isn't obvious from the subject alone. Use the standard heredoc + `Co-Authored-By` footer.

7. **Push.** Once the commit succeeds, push to `origin` on the current branch — `git push -u origin <branch>` if there's no upstream tracking yet, otherwise a plain `git push`. If the push is rejected because the remote has diverged, stop and report it; don't force-push or auto-rebase to work around it.

8. **Report back.** A short summary: what (if anything) got auto-fixed, the ticket number if one was given, the commit message used, and confirmation the push succeeded (branch + remote).
