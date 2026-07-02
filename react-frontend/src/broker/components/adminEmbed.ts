// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Scoped restyle rules for admin module pages embedded inside the broker
 * panel (wrapper > admin root div > PageHeader card):
 *  1. Hide the PageHeader's title/description block — the BrokerPageShell
 *     above already provides the broker-branded header, and a second <h1>
 *     would also hurt a11y.
 *  2. Hide the whole PageHeader card when it has no action buttons (loading
 *     states render a header without actions — without this rule an empty
 *     card would flash above the content).
 *  3. Slim the remaining actions-only card down to toolbar padding so it
 *     reads as the panel's standard toolbar row.
 * If the admin PageHeader markup ever changes, the worst-case failure mode
 * is cosmetic (a duplicate header reappears) — no behaviour is ever lost.
 */
export const EMBED_RESTYLE = [
  '[&>div>div:first-child>div>div:first-child]:hidden',
  '[&>div>div:first-child:not(:has(button))]:hidden',
  '[&>div>div:first-child]:p-2',
].join(' ');

/**
 * Additional rule for embeds whose PageHeader carries an admin-only action
 * as its FIRST button (e.g. the content queue's moderation-settings button —
 * settings are tenant policy and stay admin-gated server-side). Hiding it for
 * broker-role users avoids a dead control; the API still 403s if the CSS
 * ever fails, so this is cosmetic defence only.
 */
export const EMBED_HIDE_FIRST_HEADER_BUTTON =
  '[&>div>div:first-child_button:first-of-type]:hidden';
