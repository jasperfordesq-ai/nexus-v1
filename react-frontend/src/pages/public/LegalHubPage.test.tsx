// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for LegalHubPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render } from '@/test/test-utils';
import React from 'react';

vi.mock('@/contexts', () => ({
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    branding: { name: 'Test Community' },
    tenantPath: (p: string) => `/test${p}`,
  })),
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/components/seo', () => ({ PageMeta: () => null }));
vi.mock('framer-motion', () => {
  const proxy = new Proxy({}, {
    get: (_t: object, prop: string | symbol) => {
      return React.forwardRef(({ children, ...p }: any, ref: any) => {
        const c: Record<string, unknown> = {};
        for (const [k, v] of Object.entries(p)) {
          if (!['variants','initial','animate','exit','transition','whileHover','whileTap','whileInView','layout','viewport'].includes(k)) c[k] = v;
        }
        return React.createElement(typeof prop === 'string' ? prop : 'div', { ...c, ref }, children);
      });
    },
  });
  return { motion: proxy, AnimatePresence: ({ children }: any) => children };
});

import { LegalHubPage } from './LegalHubPage';

describe('LegalHubPage', () => {
  beforeEach(() => { vi.clearAllMocks(); });

  it('renders without crashing', () => {
    const { container } = render(<LegalHubPage />);
    expect(container.querySelector('div')).toBeTruthy();
  });
});
