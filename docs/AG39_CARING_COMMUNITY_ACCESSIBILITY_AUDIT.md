# AG39 Caring Community Accessibility Audit

Date: 2026-04-29

Scope:
- `CaringCommunityPage`
- `RequestHelpPage`
- `MySupportRelationshipsPage`
- `InviteRedemptionPage`

## Findings Fixed

### Nested interactive controls

`CaringCommunityPage` rendered primary actions as links containing HeroUI buttons. That creates nested interactive elements and can confuse keyboard and screen-reader users. The primary action controls now render as a single HeroUI `Button` with `as={Link}`.

### Keyboard focus visibility

The Caring Community module-card links now include an explicit `focus-visible` ring using the tenant theme token. This makes keyboard navigation visible on the card grid.

### Live status and error announcement

`RequestHelpPage`, `MySupportRelationshipsPage`, and `InviteRedemptionPage` now mark loading and error regions with `role="status"`, `aria-live`, `aria-busy`, or `role="alert"` as appropriate, so screen-reader users are not left waiting silently.

### Form semantics

`RequestHelpPage` now uses a real form submit path. The submit button is `type="submit"`, the handler prevents default submission, and the character-limit validation has a translated error message.

### Icon semantics

Decorative invite-state icons are now hidden from assistive technology.

### Translated status labels

Recent support-log status chips no longer expose raw API values directly. They use translated labels with a safe fallback.

## Residual Risk

This was a code-level accessibility pass, not a full assistive-technology lab walkthrough. The next deeper pass should run browser axe checks and manual keyboard/screen-reader checks against a live tenant with representative data.
