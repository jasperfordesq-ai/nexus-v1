# Releases

This document describes how Project NEXUS is versioned and released. For the change history itself, see [CHANGELOG.md](CHANGELOG.md).

## Versioning

Project NEXUS follows [Semantic Versioning](https://semver.org/) (`MAJOR.MINOR.PATCH`).

- **MAJOR** — incompatible API or data-model changes that require migration effort by adopters.
- **MINOR** — backward-compatible new features and modules.
- **PATCH** — backward-compatible fixes and small improvements.

The canonical version is the root **`VERSION`** file. Every release-relevant change updates `CHANGELOG.md` under `[Unreleased]` before the version is cut.

## What a version bump touches

The version is referenced in ~16 places (core config, README, the in-app footer label across 11 languages, the OpenAPI/release metadata, etc.). These are kept consistent by the **`check:version`** CI gate, which fails the build and names any file left at the old version. To cut a release, bump the version everywhere and run:

```bash
npm run check:version   # must print "Version consistency OK (X.Y.Z)"
npm --prefix react-frontend run copy-changelog
```

A partial bump cannot merge — the gate (in `.github/workflows/ci.yml`) blocks it.

## Release cadence & branch strategy

- Development happens directly on **`main`**; there are no long-lived release branches.
- Releases are cut from `main` when a coherent set of changes is ready — there is no fixed calendar cadence.
- Production runs the latest released version via a **zero-downtime blue/green deploy** (see [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)). Committing/tagging a release is independent of deploying it.

## Release artifacts

- A **GitHub Release** is created from the version tag (`v X.Y.Z`); see [.github/RELEASE_PROCESS.md](.github/RELEASE_PROCESS.md).
- The **CHANGELOG** `[Unreleased]` section is promoted to a dated `[X.Y.Z]` section with compare links.
- The in-app Changelog page and the footer "Generally Available (vX.Y.Z)" label update from the same sources.

## Supported versions & security

Only the current release on `main` is supported. Security issues are handled through the private process in [SECURITY.md](SECURITY.md), which lists the supported version. There is no separate long-term-support track.

## Upgrading

Because the platform is a self-hostable server application (not a library), "upgrading" means deploying a newer build. Database changes ship as idempotent Laravel migrations that run automatically on deploy (see [docs/DATABASE.md](docs/DATABASE.md)). Review the `CHANGELOG.md` for any operational notes before deploying a new version.
