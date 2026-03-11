// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { OfflineIndicator } from './OfflineIndicator';

vi.mock('framer-motion', () => {
  const handler = {
    get: (_: unknown, tag: string) => {
      return ({ children, _initial, _animate, _exit, _transition, _variants, ...rest }: Record<string, unknown>) => {
        const Tag = typeof tag === 'string' ? tag : 'div';
        return <Tag {...rest}>{children}</Tag>;
      };
    },
  };
  return {
    motion: new Proxy({}, handler),
    AnimatePresence: ({ children }: { children: React.ReactNode }) => children,
  };
});

describe('OfflineIndicator', () => {
  it('renders without crashing', () => {
    const { container } = render(<OfflineIndicator />);
    expect(container).toBeInTheDocument();
  });

  it('does not show banner when online', () => {
    // navigator.onLine defaults to true in jsdom
    Object.defineProperty(navigator, 'onLine', { value: true, writable: true });
    render(<OfflineIndicator />);
    expect(screen.queryByText(/you are offline/i)).not.toBeInTheDocument();
  });

  it('shows banner when offline', () => {
    Object.defineProperty(navigator, 'onLine', { value: false, writable: true });
    render(<OfflineIndicator />);
    expect(screen.getByText(/you are offline/i)).toBeInTheDocument();
    // Restore
    Object.defineProperty(navigator, 'onLine', { value: true, writable: true });
  });

  it('banner has alert role when offline', () => {
    Object.defineProperty(navigator, 'onLine', { value: false, writable: true });
    render(<OfflineIndicator />);
    expect(screen.getByRole('alert')).toBeInTheDocument();
    Object.defineProperty(navigator, 'onLine', { value: true, writable: true });
  });
});
