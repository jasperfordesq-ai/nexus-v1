// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

export type SalesPath = '/' | '/features' | '/hosting';

export interface SalesNavItem {
  href: SalesPath | string;
  label: string;
}

export const salesNavItems: SalesNavItem[] = [
  { href: '/', label: 'Platform' },
  { href: '/features', label: 'Features' },
  { href: '/hosting', label: 'Pricing' },
  { href: 'https://hour-timebank.ie', label: 'Live App' },
  { href: 'https://github.com/jasperfordesq-ai/nexus-v1', label: 'GitHub' },
];

export function normaliseSalesPath(path: string): SalesPath {
  if (path.startsWith('/features')) {
    return '/features';
  }

  if (path.startsWith('/hosting')) {
    return '/hosting';
  }

  return '/';
}
