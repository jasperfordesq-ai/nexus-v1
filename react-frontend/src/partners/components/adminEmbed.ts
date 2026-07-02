// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Scoped restyle rules for admin module pages embedded inside the Partner
 * Timebanks panel (wrapper > admin root div > PageHeader card):
 *  1. Hide the PageHeader's title/description block — the PartnersPageShell
 *     above already provides the panel-branded header, and a second <h1>
 *     would also hurt a11y.
 *  2. Hide the whole PageHeader card when it has no action buttons (loading
 *     states render a header without actions — without this rule an empty
 *     card would flash above the content).
 *  3. Slim the remaining actions-only card down to toolbar padding so it
 *     reads as the panel's standard toolbar row.
 * Local copy of the broker panel's rules (the CSS is coupled to the admin
 * PageHeader markup, not to the broker) so the two panels can diverge.
 * If the admin PageHeader markup ever changes, the worst-case failure mode
 * is cosmetic (a duplicate header reappears) — no behaviour is ever lost.
 *
 * NOTE: do NOT add a hide-first-header-button rule here — every Partner
 * Timebanks user is a super admin, and several federation pages keep their
 * primary Save/Refresh actions in the embedded PageHeader.
 */
export const EMBED_RESTYLE = [
  '[&>div>div:first-child>div>div:first-child]:hidden',
  '[&>div>div:first-child:not(:has(button))]:hidden',
  '[&>div>div:first-child]:p-2',
].join(' ');
