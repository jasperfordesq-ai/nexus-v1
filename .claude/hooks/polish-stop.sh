#!/usr/bin/env bash
set -euo pipefail

# 1) If any "POLISH_TODO" markers exist, force Claude to continue.
if rg -n "POLISH_TODO" views httpdocs/assets 1>/dev/null; then
  echo "POLISH_TODO markers still present. Remove/fix them before stopping." >&2
  rg -n "POLISH_TODO" views httpdocs/assets | head -n 50 >&2
  exit 2
fi

# 2) Require GOV.UK classes in Civicone top-level pages (not partials/components).
mapfile -t PAGES < <(rg --files -g"*.php" views/civicone \
  | rg -v "/components/|/partials/|/layouts/|/emails/|/admin/" )

missing=()
for f in "${PAGES[@]}"; do
  if ! rg -q "govuk-" "$f"; then
    missing+=("$f")
  fi
done

if [ "${#missing[@]}" -gt 0 ]; then
  echo "Some Civicone pages still have zero GOV.UK usage (govuk-). Keep refactoring:" >&2
  printf '%s\n' "${missing[@]}" | head -n 80 >&2
  echo "Add GOV.UK page wrappers/grid/components per Design System layout + page template." >&2
  exit 2
fi

# 3) Optional: block stopping if header/footer layout wrappers aren't correct yet.
# (Adjust checks to your exact implementation once Claude has updated them.)
if ! rg -q "govuk-template" views/layouts/civicone/header.php; then
  echo "header.php missing govuk-template integration for Civicone theme." >&2
  exit 2
fi

echo "Polish gates passed: OK to stop."
exit 0
