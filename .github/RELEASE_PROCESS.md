# Release Process

Project NEXUS releases are source releases. Creating a GitHub Release does not deploy production.

## Versioning

- Use semantic versions with a `v` tag prefix, for example `v1.5.0`.
- Use pre-release suffixes for release candidates, for example `v1.5.0-rc.1`.
- Keep `README.md`, `CHANGELOG.md`, and `composer.json` aligned with the same public release line.

## Pre-Release Checklist

- Confirm `main` is green in GitHub Actions.
- Confirm dependency review, security scan, PHPStan, PHPUnit, Vitest coverage, build, migration, i18n, SPDX, E2E smoke, and accessibility gates have passed.
- Update `CHANGELOG.md` by moving relevant `Unreleased` entries under the new version heading.
- Check `SECURITY.md` still points to the correct private reporting path.
- Confirm no secrets, private reports, generated local artifacts, or backup-only files are included.

## Create a Release

```bash
git checkout main
git pull origin main
git tag -a v1.5.0-rc.1 -m "Project NEXUS v1.5.0-rc.1"
git push origin v1.5.0-rc.1
```

The `Release` workflow creates the GitHub Release from the tag and pulls notes from the matching `CHANGELOG.md` section.

## After Release

- Verify the release page rendered the expected notes.
- If the release is production-bound, wait for an explicit deployment instruction before running any deployment script.
- Do not push to the backup remote unless explicitly instructed.
