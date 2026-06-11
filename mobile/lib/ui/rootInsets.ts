// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * The bottom safe-area inset measured at the ROOT of the app.
 *
 * Inside Android `presentation: 'modal'` screens, useSafeAreaInsets()
 * reports bottom: 0, so bottom sheets and form footers rendered in modal
 * routes sat underneath the system navigation bar. The root layout records
 * the real inset here once; consumers take max(hookValue, root value).
 */
let rootBottomInset = 0;

export function setRootBottomInset(value: number): void {
  if (Number.isFinite(value) && value > rootBottomInset) {
    rootBottomInset = value;
  }
}

export function getRootBottomInset(): number {
  return rootBottomInset;
}
