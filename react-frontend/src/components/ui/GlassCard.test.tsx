// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { GlassCard } from './GlassCard';

// Mock framer-motion to strip animation props and render as regular elements
vi.mock('framer-motion', () => {
  const handler = {
    get: (_: any, tag: string) => {
      return ({ children, initial, animate, exit, transition, variants, whileHover, whileTap, whileInView, ...rest }: any) => {
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

describe('GlassCard', () => {
  it('renders children', () => {
    render(<GlassCard>Card Content</GlassCard>);
    expect(screen.getByText('Card Content')).toBeInTheDocument();
  });

  it('applies glass-card base class by default', () => {
    render(<GlassCard><span data-testid="content">Content</span></GlassCard>);
    const card = screen.getByTestId('content').parentElement;
    expect(card).toHaveClass('glass-card');
  });

  it('applies glass-card-hover class when hoverable', () => {
    render(<GlassCard hoverable><span data-testid="content">Content</span></GlassCard>);
    const card = screen.getByTestId('content').parentElement;
    expect(card).toHaveClass('glass-card-hover');
  });

  it('applies glow class when glow prop is set', () => {
    render(<GlassCard glow="primary"><span data-testid="content">Content</span></GlassCard>);
    const card = screen.getByTestId('content').parentElement;
    expect(card).toHaveClass('glow-primary');
  });

  it('does not apply glow class when glow is none', () => {
    render(<GlassCard glow="none"><span data-testid="content">Content</span></GlassCard>);
    const card = screen.getByTestId('content').parentElement;
    expect(card?.className).not.toContain('glow-');
  });

  it('applies additional className', () => {
    render(<GlassCard className="p-4 mt-2"><span data-testid="content">Content</span></GlassCard>);
    const card = screen.getByTestId('content').parentElement;
    expect(card).toHaveClass('p-4');
    expect(card).toHaveClass('mt-2');
  });

  it('handles onClick', () => {
    const handleClick = vi.fn();
    render(<GlassCard onClick={handleClick}>Clickable</GlassCard>);
    screen.getByText('Clickable').click();
    expect(handleClick).toHaveBeenCalledTimes(1);
  });

  it('renders animated variant and preserves class', () => {
    render(<GlassCard animated><span data-testid="content">Animated</span></GlassCard>);
    const card = screen.getByTestId('content').parentElement;
    expect(card).toHaveClass('glass-card');
  });

  it('applies inline styles', () => {
    render(<GlassCard style={{ maxWidth: '400px' }}><span data-testid="content">Styled</span></GlassCard>);
    const card = screen.getByTestId('content').parentElement;
    expect(card).toHaveStyle({ maxWidth: '400px' });
  });
});
