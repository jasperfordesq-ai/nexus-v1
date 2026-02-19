// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { BackToTop } from './BackToTop';

// Mock framer-motion
vi.mock('framer-motion', () => ({
  motion: new Proxy({}, {
    get: (_, tag) => {
      return ({ children, ...props }: any) => {
        const Tag = typeof tag === 'string' ? tag : 'div';
        return <Tag {...props}>{children}</Tag>;
      };
    },
  }),
  AnimatePresence: ({ children }: any) => <>{children}</>,
}));

// Mock HeroUI Button
vi.mock('@heroui/react', async () => {
  const actual = await vi.importActual('@heroui/react');
  return {
    ...actual,
    Button: ({ children, 'aria-label': ariaLabel, onPress, ...props }: any) => (
      <button aria-label={ariaLabel} onClick={onPress} {...props}>
        {children}
      </button>
    ),
  };
});

describe('BackToTop', () => {
  it('renders without crashing', () => {
    const { container } = render(<BackToTop />);
    expect(container).toBeInTheDocument();
  });

  it('is initially hidden (scrollY = 0)', () => {
    render(<BackToTop />);
    // Button should not be visible since scrollY < 400
    expect(screen.queryByLabelText('Scroll to top')).not.toBeInTheDocument();
  });
});
