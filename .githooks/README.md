# Git Hooks

This directory contains shared git hooks that enforce CLAUDE.md coding standards.

## Installation

To enable these hooks for your local repository:

```bash
git config core.hooksPath .githooks
chmod +x .githooks/pre-commit
```

## Hooks

### pre-commit

**Purpose:** Enforces CLAUDE.md CSS organization rules

**What it checks:**
1. ✅ No static inline `style=""` attributes in PHP files
2. ✅ No `element.style.*` manipulation in JavaScript files
3. ✅ Ensures use of CSS classes with `classList` instead

**Bypassing (NOT RECOMMENDED):**
```bash
git commit --no-verify
```

## Why These Rules?

Per `CLAUDE.md`:
- **NEVER** write inline `<style>` blocks in PHP/HTML files
- **NEVER** use inline `style=""` attributes (except truly dynamic values)
- All CSS goes in `/httpdocs/assets/css/`
- JavaScript should use `classList` API instead of direct style manipulation

This keeps styles centralized, maintainable, and PurgeCSS-compatible.

## Troubleshooting

**Hook not running?**
```bash
# Check hook path
git config core.hooksPath

# Should output: .githooks

# If empty, run:
git config core.hooksPath .githooks
```

**"Permission denied" error?**
```bash
chmod +x .githooks/pre-commit
```

## For CI/CD

Add to your CI pipeline:

```yaml
# Example: GitHub Actions
- name: Lint inline styles
  run: npm run lint:php:styles

- name: Lint JavaScript
  run: npm run lint:js
```
