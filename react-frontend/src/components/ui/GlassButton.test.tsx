// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { GlassButton } from './GlassButton';

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

describe('GlassButton', () => {
  it('renders children text', () => {
    render(<GlassButton>Click Me</GlassButton>);
    expect(screen.getByText('Click Me')).toBeInTheDocument();
  });

  it('applies glass-button base class', () => {
    render(<GlassButton>Button</GlassButton>);
    const button = screen.getByText('Button').closest('button');
    expect(button).toHaveClass('glass-button');
  });

  it('applies size class', () => {
    render(<GlassButton size="lg">Large</GlassButton>);
    const button = screen.getByText('Large').closest('button');
    expect(button).toHaveClass('glass-button-lg');
  });

  it('applies variant class', () => {
    render(<GlassButton variant="primary">Primary</GlassButton>);
    const button = screen.getByText('Primary').closest('button');
    expect(button).toHaveClass('glass-button-primary');
  });

  it('sets type attribute', () => {
    render(<GlassButton type="submit">Submit</GlassButton>);
    const button = screen.getByText('Submit').closest('button');
    expect(button).toHaveAttribute('type', 'submit');
  });

  it('handles click events', () => {
    const handleClick = vi.fn();
    render(<GlassButton onClick={handleClick}>Click</GlassButton>);
    screen.getByText('Click').click();
    expect(handleClick).toHaveBeenCalledTimes(1);
  });

  it('can be disabled', () => {
    render(<GlassButton disabled>Disabled</GlassButton>);
    const button = screen.getByText('Disabled').closest('button');
    expect(button).toBeDisabled();
  });

  it('renders as regular button when disabled (not animated)', () => {
    render(<GlassButton disabled animated>Disabled Animated</GlassButton>);
    const button = screen.getByText('Disabled Animated').closest('button');
    expect(button).toBeDisabled();
  });

  it('applies fullWidth class', () => {
    render(<GlassButton fullWidth>Full Width</GlassButton>);
    const button = screen.getByText('Full Width').closest('button');
    expect(button).toHaveClass('w-full');
  });
});
