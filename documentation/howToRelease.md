<!-- deployed from Glawster/organiseMyProjects release 0.9.1 -- do not edit directly -->
# Release Process

## Purpose

This guide defines a safe, repeatable release workflow for this repository.

## Versioning Policy

- Released versions are immutable once published.
- Existing tags `0.3` and `0.4` are retained as historical release tags.
- Standard release tags use `vX.Y`.
- Patch tags `vX.Y.Z` are allowed when needed for exceptional hotfix releases.
- Development versions use the next intended release number with an alpha
  suffix: `1.4a1`, `1.4a2`, and so on while the current release is `1.3`.
- `1.3a1` precedes `1.3`; it must not identify development after `1.3`.
- Start at `a1` for each new target release and increment the suffix for each
  distinct development build that is shared or published. Do not reuse a
  published development version for different code.
- Every development branch has its own alpha number, so a version always
  identifies one branch's work. A number is unused only when no `v*` tag, no
  `main`, no release branch and no other local or remote branch holds it:
  check them all (`git fetch --tags --prune origin`, then the version source on
  each branch) before taking one, and push the new branch promptly so other
  checkouts see the number.
- Keep the authoritative version source, package metadata and reported CLI/log
  version consistent. Git commit and dirty-state details may supplement the
  version, but must not cause development code to report the previous release.
- Tie each development version to its branch with an annotated tag such as
  `v1.4a1` on the commit that completes the branch's work: its merge into the
  release branch (or into `main`, where a project has no release branches).
  The tag message names the version and the pull request or branch, for
  example `1.4a1: Add requirement board (#74)`, so `git tag -n1` lists each
  version with its work. Final releases omit the alpha suffix: version `1.4`,
  tag `v1.4`.

## Branch Model

- `main` represents the latest released production state.
- Active development continues on release branches such as `release/1.4`,
  with feature branches integrated there for the same target release.
- Immediately after creating any new development branch, update `version.py`
  as the first change, before implementation work. For OMP this is
  `organiseMyProjects/version.py`; use the equivalent authoritative version
  source in projects with a different layout.
- For the first branch targeting the next release, set the version to `1.4a1`
  when `main` is `1.3`. For subsequent development branches targeting `1.4`,
  use the next unused alpha number, such as `1.4a2`; do not reset to `a1`.
  Increment again for each further shared development build.
- `main` keeps the current final release version, for example `1.3`, while
  development branches work towards `1.4`.
- When the version source conflicts as a branch is integrated, the release
  branch takes the higher number, so it never goes back to an earlier
  development version.
- A project that deviates from this model, for example by squash-merging
  each branch straight into `main` with no release branches, records the
  exception in `.github/additional-instructions.md`; the alpha numbering, one
  number per branch, and the tagging rules above still apply.
- Changes land on `main` only as a complete, validated release. Remove the
  alpha suffix on the release branch during final release preparation, then
  merge and tag that final version on `main`.

Example sequence: `main` is `1.3`; `release/1.4` progresses through `1.4a1`
and `1.4a2`; the approved release becomes `1.4` on `main`, tagged `v1.4`.
Subsequent development starts at `1.5a1` (or `1.4.1a1` for a hotfix).

## Repository Protection Rules

- Protect `main` from force pushes and deletion.
- Require pull requests to merge into `main`.
- Protect release tags with a Tag Ruleset so existing tags cannot be moved or
   deleted.
- Use a ruleset target pattern that matches release tags, for example `v*`.

## Release Checklist

1. Integrate all work intended for the release into its release branch.
   Replace its alpha version (for example `1.4a2`) with the final version
   (`1.4`), update the release notes, and confirm CI is green.
2. Run local validation inside the project's Conda environment:
   - `pytest -rs`, confirming no tests were skipped for a missing dependency
   - `runLinter .`, which must exit `0`
   - `runLinter --markup`
   - If markup issues are reported, run `runLinter --markup --fix` and then
     rerun `runLinter --markup`.
3. Confirm the project's authoritative version source (for OMP,
   `organiseMyProjects/version.py`), package metadata, CLI/log version output,
   release notes and documentation all identify the intended final release.
4. Merge the release branch into `main` through a pull request.
5. Pull latest `main` locally with `git pull --ff-only`.
6. Tag the released branch's final commit on `main` with an annotated Git
   tag matching the final version in `version.py`. The tag permanently
   identifies the released code:
   - `git tag -a v1.4 -m "Release v1.4"`
7. Push the tag:
   - `git push origin v1.4`
8. Verify the GitHub release artifacts and notes.

## Hotfix Workflow

1. Branch from the current release on `main`, for example `hotfix/1.4.1`,
   and update `version.py` to `1.4.1a1` as the first change before fixing code.
2. Apply the fix, increment the alpha number for shared development builds,
   and validate tests.
3. Set the final version to `1.4.1`, update release notes, complete the release
   checklist, and merge into `main` with a pull request.
4. Tag the released `main` commit with an annotated `v1.4.1` tag.
5. Cherry-pick or merge the fix into active release branches as needed.

## Temporary Tag-Rule Rollback Checklist

Use this checklist only when a protected release tag must be corrected.

1. Identify and record the intended target commit hash.
2. In GitHub Rulesets, temporarily allow tag update or deletion for the exact
   tag (or use an admin bypass if your ruleset allows it).
3. Delete the existing remote tag:
   - `git push origin :refs/tags/v1.4`
4. Delete the local tag copy:
   - `git tag -d v1.4`
5. Recreate the tag at the intended commit:
   - `git tag -a v1.4 <commit> -m "Release v1.4"`
6. Push the corrected tag:
   - `git push origin v1.4`
7. Verify the tag points to the expected commit locally and remotely.
8. Re-enable strict tag protection immediately.

Rulesets note:

- GitHub legacy Protected Tags are deprecated; manage release-tag protections in
   Repository Rulesets.

Verification commands:

- `git rev-parse v1.4^{}`
- `git ls-remote --tags origin v1.4 v1.4^{}`

## Tag Conflict Prevention

- Never reuse an existing release tag name.
- Never retag a published version.
- Use annotated tags for all releases.
- If a mistaken local tag exists, back it up before replacing it.
