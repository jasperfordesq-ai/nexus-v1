// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi } from 'vitest';
import { render, screen, act } from '@/test/test-utils';
import { SessionExpiredModal } from './SessionExpiredModal';

vi.mock('framer-motion', () => {
  const handler = {
    get: (_: any, tag: string) => {
      return ({ children, initial, animate, exit, transition, variants, whileHover, whileTap, ...rest }: any) => {
        const Tag = typeof tag === 'string' ? tag : 'div';
        return <Tag {...rest}>{children}</Tag>;
      };
    },
  };
  return {
    motion: new Proxy({}, handler),
    AnimatePresence: ({ children }: any) => children,
  };
});

vi.mock('@/lib/api', () => ({
  SESSION_EXPIRED_EVENT: 'nexus:session_expired',
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
  tokenManager: { getTenantId: vi.fn(), getAccessToken: vi.fn() },
}));

vi.mock('@/contexts', () => ({
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test', slug: 'test' },
    tenantPath: vi.fn((p: string) => `/test${p}`),
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    isLoading: false,
    branding: { name: 'Test', primary_color: '#4F46E5' },
    tenantSlug: 'test',
  })),
}));

describe('SessionExpiredModal', () => {
  it('renders without crashing (modal initially hidden)', () => {
    const { container } = render(<SessionExpiredModal />);
    expect(container).toBeInTheDocument();
  });

  it('does not show modal content by default', () => {
    render(<SessionExpiredModal />);
    expect(screen.queryByText('Session Expired')).not.toBeInTheDocument();
  });

  it('shows modal when session expired event fires', () => {
    render(<SessionExpiredModal />);
    act(() => {
      window.dispatchEvent(new CustomEvent('nexus:session_expired'));
    });
    expect(screen.getByText('Session Expired')).toBeInTheDocument();
    expect(screen.getByText(/session has expired/i)).toBeInTheDocument();
  });

  it('shows Dismiss and Log In buttons when modal is open', () => {
    render(<SessionExpiredModal />);
    act(() => {
      window.dispatchEvent(new CustomEvent('nexus:session_expired'));
    });
    expect(screen.getByText('Dismiss')).toBeInTheDocument();
    expect(screen.getByText('Log In')).toBeInTheDocument();
  });
});
