// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { legalPages, type LegalPath } from '../data/legal';

export type SalesPath = '/' | '/features' | '/hosting' | LegalPath;

export interface SalesNavItem {
  href: SalesPath | string;
  label: string;
}

export const salesNavItems: SalesNavItem[] = [
  { href: '/', label: 'Platform' },
  { href: '/features', label: 'Features' },
  { href: '/hosting', label: 'Pricing' },
  { href: 'https://hour-timebank.ie', label: 'Live App' },
];

export function normaliseSalesPath(path: string): SalesPath {
  const legalMatch = legalPages.find((page) => path.startsWith(page.path));

  if (legalMatch) {
    return legalMatch.path;
  }

  if (path.startsWith('/features')) {
    return '/features';
  }

  if (path.startsWith('/hosting')) {
    return '/hosting';
  }

  return '/';
}
