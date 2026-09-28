# Main-branch protection

`main-pr-ci-v1.json` is the importable template. **A committed JSON file is not itself an active ruleset.** The owner subsequently installed it: the 2026-09-28 API readback confirmed ruleset **24108510**, active for `refs/heads/main`, with no bypass actors. PR #1 is merged. See [verification record](../VERIFICATION-12.1.3.md).

## Enforced configuration

A pull request, current base, resolved review conversations and a passing `required-checks` result from GitHub Actions (integration ID 15368) are required. Main deletion and force-push are blocked. The ruleset was not weakened to integrate subsequent changes.

Required approving reviews remain zero for the sole-maintainer workflow. This is not independent approval. Add an eligible second reviewer before requiring an approval; an author cannot approve their own PR. Administrators can still edit rulesets, so account security remains essential.

The aggregate job now depends on isolated regression tests, real WordPress integration and Plugin Check. Changes to those workflows need careful review; protection cannot replace code review or ensure CI itself is trustworthy.

## Recreate only when needed

Do not create a duplicate ruleset in this repository. For intentional recreation, review the latest template and use GitHub **Settings > Rules > Rulesets > Import a ruleset**, then read back the resulting effective rules. Confirm required checks are available before enforcing them. Never add bypass actors merely to pass a failing change.

For an administrator using an already authenticated local CLI, read the actual state with:

```sh
gh api repos/dominiczajac/email-countdown-timer/rulesets/24108510
gh api repos/dominiczajac/email-countdown-timer/rules/branches/main
```

Never commit or paste tokens. Contents write access and repository Administration write access are distinct. The connected editor did not install this ruleset; the owner did.

Sources: [GitHub repository rules API](https://docs.github.com/en/rest/repos/rules), [managing rulesets](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-rulesets/managing-rulesets-for-a-repository).
