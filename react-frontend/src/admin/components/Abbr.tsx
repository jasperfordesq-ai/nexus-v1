// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin-only abbreviation tooltip component.
 * Renders with a dotted underline and a HeroUI Tooltip showing the full definition.
 * Admin panel is English-only — no i18n.
 */

import { Tooltip } from '@heroui/react';

export const ABBR_TERMS: Record<string, string> = {
  KISS: 'Koordination und Innovation für Soziales — Swiss methodology for community-based care coordination, developed with Age-Stiftung',
  AGORIS: 'Vision-stage Swiss deployment of NEXUS under the KISS methodology',
  CHF: 'Swiss Franc — the currency used for formal-care cost-offset calculations in KISS deployments',
  FADP: 'Federal Act on Data Protection — revised Swiss data protection law (in force since Sep 2023)',
  nDSG: 'neues Datenschutzgesetz — the revised Swiss Federal Data Protection Act (synonym for FADP)',
  DSG: 'Datenschutzgesetz — see nDSG / FADP',
  GDPR: 'General Data Protection Regulation — EU data protection law; Swiss deployments use FADP/nDSG instead',
  SLA: 'Service Level Agreement — the maximum committed response or resolution time for each priority tier',
  AGPL: 'GNU Affero General Public License v3 — the open-source licence under which NEXUS is publicly released',
  KPI: 'Key Performance Indicator — a measurable metric used to evaluate progress toward a defined goal',
  ROI: 'Return on Investment — here: formal-care cost avoided per hour of informal support exchanged',
  NEXUS: 'Project NEXUS — the multi-tenant timebanking and community care platform',
  AGM: 'Annual General Meeting',
  XP: 'Experience Points — gamification currency awarded for completing exchanges and reaching milestones',
  ISCO: 'International Standard Classification of Occupations — used to categorise care service types',
};

interface AbbrProps {
  /** Key from ABBR_TERMS dictionary */
  term: keyof typeof ABBR_TERMS;
  /** Override the displayed text; defaults to the term key itself */
  children?: string;
  /** Extra CSS classes on the inner span */
  className?: string;
}

/**
 * Usage:
 *   <Abbr term="KISS" />                  → renders "KISS" with tooltip
 *   <Abbr term="CHF">CHF 35/hr</Abbr>     → renders "CHF 35/hr" with CHF tooltip
 */
export function Abbr({ term, children, className }: AbbrProps) {
  const definition = ABBR_TERMS[term];
  if (!definition) return <>{children ?? term}</>;

  return (
    <Tooltip
      content={
        <span className="max-w-xs block text-xs leading-relaxed">
          <strong>{term}:</strong> {definition}
        </span>
      }
      placement="top"
      delay={300}
    >
      <abbr
        title={definition}
        className={`cursor-help border-b border-dotted border-current no-underline ${className ?? ''}`}
        style={{ textDecorationLine: 'none' }}
      >
        {children ?? term}
      </abbr>
    </Tooltip>
  );
}

export default Abbr;
