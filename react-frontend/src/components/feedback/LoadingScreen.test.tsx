// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { LoadingScreen } from './LoadingScreen';

vi.mock('@/lib/motion', () => {
  const handler = {
    get: (_: unknown, tag: string) => {
      return ({ children, _initial, _animate, _exit, _transition, _variants, _whileHover, _whileTap, ...rest }: Record<string, unknown>) => {
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

describe('LoadingScreen', () => {
  it('renders with default loading message', () => {
    render(<LoadingScreen />);
    // The message appears twice (visible + sr-only), use getAllByText
    const elements = screen.getAllByText('Loading...');
    expect(elements.length).toBeGreaterThanOrEqual(1);
  });

  it('renders with custom message', () => {
    render(<LoadingScreen message="Checking authentication..." />);
    const elements = screen.getAllByText('Checking authentication...');
    expect(elements.length).toBeGreaterThanOrEqual(1);
  });

  it('has accessible status role', () => {
    render(<LoadingScreen />);
    // The ambient ToastProvider also renders status regions, so scope to
    // LoadingScreen's own status (the one marked aria-busy).
    const status = screen.getAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true');
    expect(status).toBeInTheDocument();
  });

  it('has aria-busy attribute', () => {
    render(<LoadingScreen />);
    const status = screen.getAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true');
    expect(status).toHaveAttribute('aria-busy', 'true');
  });

  it('includes screen reader text', () => {
    render(<LoadingScreen message="Please wait" />);
    const srOnly = document.querySelector('.sr-only');
    expect(srOnly).toHaveTextContent('Please wait');
  });
});
