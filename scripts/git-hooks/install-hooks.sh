#!/usr/bin/env bash
#
# Installs the repo's git hooks into .git/hooks.
#
# Run once per clone/machine:
#   bash scripts/git-hooks/install-hooks.sh
#
# Currently installs:
#   - pre-commit : runs any *staged* PHP test files before a commit is created,
#     so broken auto-generated/coverage test batches can't land on `main` and
#     turn CI red. Skips gracefully when php/phpunit or the test DB are absent.
#
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel)"
SRC="$ROOT/scripts/git-hooks"
DEST="$ROOT/.git/hooks"

for hook in pre-commit; do
  if [ -f "$SRC/$hook" ]; then
    cp "$SRC/$hook" "$DEST/$hook"
    chmod +x "$DEST/$hook"
    echo "installed: .git/hooks/$hook"
  fi
done

echo "Done. The pre-commit test verify-gate is active."
