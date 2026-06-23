# Project NEXUS Architecture

Last reviewed: 2026-06-23
Platform version: 1.5.2

This document is the maintained architecture map for Project NEXUS. It is intentionally compact: use it to understand the runtime boundaries, primary code paths, and documents to read next.

## System Shape

Project NEXUS is a multi-tenant community platform for timebanking and adjacent community-exchange workflows. The production system is a Laravel 12 API/backend, a React 19 primary frontend, an HTML-first accessible frontend, MariaDB, Redis, Meilisearch, Pusher, Firebase Cloud Messaging, and supporting deployment/observability tooling.

```text
Users and admins
  |
  +-- React SPA at app.project-nexus.ie
  |     |
  |     +-- Laravel API routes in routes/api.php
  |
  +-- Accessible HTML frontend at accessible.project-nexus.ie
        |
        +-- Laravel controllers under app/Http/Controllers/GovukAlpha/

Laravel 12 application
  |
  +-- Eloquent models and services under app/
  +-- Tenant context, middleware, auth, feature gates
  +-- MariaDB 10.11, Redis 7, Meilisearch, Pusher, FCM
  +-- Blue/green deployment on Apache/Plesk/Azure
```

## Runtime Boundaries

| Surface | Primary path | Responsibility |
| --- | --- | --- |
| React app | `react-frontend/` | Main member UI, current admin UI, PWA shell, translated client experience. |
| Accessible frontend | `accessible-frontend/`, `app/Http/Controllers/GovukAlpha/` | HTML-first tenant UI for users who benefit from simpler progressive enhancement. |
| Laravel API | `routes/api.php`, `app/Http/Controllers/Api/` | JSON API for React, mobile, integrations, and admin operations. |
| Domain services | `app/Services/` | Business rules for listings, exchanges, federation, volunteering, messages, notifications, reporting, and adjacent modules. |
| Data model | `database/migrations/`, `database/schema/mysql-schema.sql`, `migrations/` | Current Laravel migrations, schema dump, and historical SQL migration record. |
| Public web root | `httpdocs/` | Apache entrypoints, health endpoints, version endpoint, and compatibility routing. |
| Legacy views | `views/` | Retired PHP UI except the documented live email and module-404 exceptions. |

## Tenant and Feature Model

All business logic must preserve tenant isolation. PHP code should resolve tenant scope through the established tenant context/middleware patterns, and React code should use the tenant context/hooks already present in `react-frontend/src/`.

Feature availability is tenant-configured. User-facing routes, API actions, accessible frontend pages, notifications, search entries, and navigation should all check the same feature gate rather than assuming a module is globally enabled.

## User Interfaces

The React frontend is the primary UI. It uses React 19, TypeScript, HeroUI v3, Tailwind CSS 4, Lucide icons, translation namespaces, CSS tokens, and the local motion shim. New user-facing UI belongs here unless it is specifically part of the accessible frontend.

The accessible frontend is a maintained second surface, not legacy PHP. It uses GOV.UK Frontend markup/classes/Sass/JS with Project NEXUS branding and attribution. Its controller and translation paths must stay isolated from the React app while preserving the same tenant, module, auth, and AGPL attribution rules.

## Backend Organization

Laravel is the sole HTTP handler. Controllers should stay thin and delegate business rules to services. Services should follow existing static/service patterns, tenant scoping, and database conventions already used under `app/Services/`.

New schema changes should use Laravel migrations in `database/migrations/`. The root `migrations/` directory is historical; do not add new legacy SQL migrations.

## Cross-Cutting Requirements

| Requirement | Enforcement |
| --- | --- |
| Translations | End-user React text uses `t(...)`; email/notification PHP text uses translation keys and recipient locale wrapping. |
| Tenant isolation | Middleware, tenant context, scoped queries, and feature gates. |
| Open-source attribution | AGPL Section 7(b) footer/about attribution and `NOTICE` terms. |
| Version consistency | `VERSION` plus `npm run check:version`. |
| Documentation hygiene | `docs/README.md` plus `npm run check:docs`. |
| Changelog discipline | `CHANGELOG.md` under `[Unreleased]`, then `npm --prefix react-frontend run copy-changelog`. |

## Operations and Deployment

Production runs on Apache/Plesk/Azure using the blue/green deployment engine under `scripts/deploy/`. Do not deploy without an explicit user instruction. The maintained deployment reference is [DEPLOYMENT.md](DEPLOYMENT.md); incident response and observability references are [RUNBOOK-INCIDENTS.md](RUNBOOK-INCIDENTS.md), [MONITORING.md](MONITORING.md), [SLO.md](SLO.md), and [SENTRY.md](SENTRY.md).

Routine Windows development uses native Laragon Apache/PHP and native Vite, with Docker used primarily for data services. Docker PHP/frontend profiles exist for container testing and production parity.

## Documentation Sufficiency

The documentation is now adequate as a maintained baseline for the current platform if the hygiene checks keep passing. It covers setup, topology, deployment, incident response, monitoring, SLOs, Sentry, federation, custom domains, accessible frontend constraints, contributor terms, versioning, and changelog discipline.

The remaining documentation risk is depth, not tidiness: large modules such as wallet/exchange lifecycle, notifications, search, federation operations, and mobile packaging may still need curated module guides when they next receive material changes. Add those as small maintained pages only when they become useful to future maintenance.
