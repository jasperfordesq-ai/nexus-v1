// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for CookiesPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';

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
vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: Record<string, unknown>) => {
      const { variants, initial, animate, layout, ...rest } = props;
      return <div {...rest}>{children}</div>;
    },
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

import { CookiesPage } from './CookiesPage';

describe('CookiesPage', () => {
  beforeEach(() => { vi.clearAllMocks(); });

  it('renders without crashing', () => {
    render(<CookiesPage />);
    expect(screen.getByText(/Cookie Policy/i)).toBeInTheDocument();
  });
});
