# Security Scanning

Last reviewed: 2026-06-23

Project NEXUS is a public AGPL repository. Security scanning must distinguish reachable production risk from development-tooling noise.

## Security Sources Of Truth

| Document or file | Purpose |
| --- | --- |
| `SECURITY.md` | Public vulnerability disclosure policy. |
| `.github/workflows/security-scan.yml` | CI security scan workflow. |
| `owasp-suppressions.xml` | OWASP Dependency-Check suppressions with reasons. |
| `.trivyignore` | Trivy suppressions with reasons. |
| `composer.lock`, `package-lock.json`, `react-frontend/package-lock.json` | Dependency state that scanners evaluate. |

## Scanner Interpretation

A finding deserves action when all of these are true:

- it is in code or a dependency that ships to production;
- an untrusted user can reach the vulnerable path;
- it is high or critical severity, or it affects authentication, authorization, crypto, sanitization, serialization, tenant isolation, or money movement.

Findings in dev servers, test-only packages, mobile-only dependencies, debug renderers, build tools, or wrong-package CPE matches may still be recorded, but should be suppressed with a clear reason if they repeatedly obscure real risk.

## Public-Safe Suppression Rules

- Do not include secrets, private contacts, live IP addresses, or credential paths in suppressions.
- Explain why a suppressed finding is not reachable.
- Review suppressions when a dependency moves from development tooling into production runtime.
- Prefer upgrading security-sensitive packages even when exposure is low.

## Related Checks

```bash
npm run check:docs
npm run check:version
```

Run the workflow-defined security checks in CI for release decisions. Do not run repeated local audits merely to recreate the same known noise classes.
