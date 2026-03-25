// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

export const TIMING = {
  quick: 150,
  base: 250,
  slow: 400,
} as const;

export const SPRING_CONFIGS = {
  gentle: { damping: 15, stiffness: 150, mass: 1 },
  bouncy: { damping: 8, stiffness: 200, mass: 0.8 },
  stiff: { damping: 20, stiffness: 300, mass: 1 },
} as const;
