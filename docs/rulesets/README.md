# Main-branch protection

`main-pr-ci-v1.json` is an importable GitHub branch ruleset. **A committed JSON file is not an active ruleset.** At the 2026-09-28 readback, this repository had no rulesets and `main` reported `protected: false`. The connected editor did not expose an Administration write operation; no settings were changed.

## Apply once, as the repository administrator

1. Merge PR #1 through GitHub after reviewing it and checking its latest CI. It installs the `required-checks` job; do not activate a check requirement that your development branches cannot produce.
2. In this repository, open **Settings > Rules > Rulesets > New ruleset > Import a ruleset** and select this JSON file.
3. Review the target (`refs/heads/main`) and leave enforcement **Active**. Save the ruleset. Do not add a bypass actor simply to get a failing change through.
4. Reopen the ruleset and verify the effective rules for `main`. The file itself and merging this PR are not evidence of enforcement.

The rules require a pull request, an up-to-date branch, resolved review conversations, and a successful `required-checks` result from GitHub Actions (integration ID 15368, verified against this repository's check run). They block force-pushes and deletion of `main`.

There are zero required approving reviews because the sole maintainer cannot approve their own PR. This does **not** make self-review independent. Add an eligible second maintainer before requiring one approval. There is no configured bypass list. Administrators can still change the ruleset itself; repository/account security remains necessary.

Signed commits, merge queues, deployments, CodeQL, and approval-after-last-push are intentionally not required because this repository has not established those gates. A status-check gate is not a security certification, and changes to CI itself require careful review.

For an administrator who already uses an authenticated GitHub CLI, the alternative is a **single** create operation (do not run it repeatedly or after importing the file):

```sh
gh api --method POST repos/dominiczajac/email-countdown-timer/rulesets \
  --input docs/rulesets/main-pr-ci-v1.json

gh api repos/dominiczajac/email-countdown-timer/rules/branches/main
```

Never paste an access token into a chat or commit it. Creating a ruleset requires repository Administration write permission; content-write access alone is insufficient.

Sources: [GitHub repository rules API](https://docs.github.com/en/rest/repos/rules), [managing rulesets](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-rulesets/managing-rulesets-for-a-repository).
