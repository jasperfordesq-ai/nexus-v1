// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { OfflineIndicator } from './OfflineIndicator';

vi.mock('framer-motion', () => {
  const handler = {
    get: (_: any, tag: string) => {
      return ({ children, initial, animate, exit, transition, variants, ...rest }: any) => {
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
