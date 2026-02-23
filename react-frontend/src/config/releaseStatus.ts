// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Platform release status configuration.
 *
 * To change the stage:
 * - Update `stageKey`, `stageLabel`, and `stageSummary` here.
 * - The banner and status page update automatically.
 * - Set stageKey to 'ga' (General Availability) to indicate the platform is
 *   no longer in pre-release. You can then remove the banner from Layout.tsx
 *   if desired.
 */
export const RELEASE_STATUS = {
  stageKey: 'rc' as const,
  stageLabel: 'Release Candidate (RC)',
  stageSummary: "Core features are working. We're in final hardening and would love help testing.",
  readMorePath: '/development-status',
} as const;

export type ReleaseStageKey = typeof RELEASE_STATUS.stageKey;
