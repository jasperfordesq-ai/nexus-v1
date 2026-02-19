// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for TermsPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import React from 'react';

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn().mockResolvedValue({ success: true, data: null }) },
  tokenManager: { getTenantId: vi.fn() },
}));

vi.mock('@/contexts', () => ({
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    branding: { name: 'Test Community' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
  })),
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/hooks/useLegalDocument', () => ({
  useLegalDocument: vi.fn(() => ({ document: null, loading: false })),
}));
vi.mock('@/components/seo', () => ({ PageMeta: () => null }));
vi.mock('@/components/legal/CustomLegalDocument', () => ({
  default: () => <div data-testid="custom-legal">Custom Legal Doc</div>,
  CustomLegalDocument: () => <div data-testid="custom-legal">Custom Legal Doc</div>,
}));
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

import { TermsPage } from './TermsPage';

describe('TermsPage', () => {
  beforeEach(() => { vi.clearAllMocks(); });

  it('renders without crashing', () => {
    render(<TermsPage />);
    const matches = screen.getAllByText(/Terms of Service/i);
    expect(matches.length).toBeGreaterThan(0);
  });
});
