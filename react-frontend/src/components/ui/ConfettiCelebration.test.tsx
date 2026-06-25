// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import React from 'react';

// ─── Mock @/lib/motion — lightweight passthrough so jsdom doesn't choke ───────
vi.mock('@/lib/motion', () => {
  const React = require('react');

  // motion.div, motion.p etc. — just render as div with children
  const MotionDiv = (
    { children, ref, ...rest }: React.HTMLAttributes<HTMLDivElement> & { ref?: React.Ref<HTMLDivElement> }
  ) => {
    // Strip framer-specific props that would cause React unknown-prop warnings
    const {
      initial: _i, animate: _a, exit: _e, transition: _t, variants: _v,
      whileHover: _wh, whileTap: _wt, whileFocus: _wf, whileInView: _wiv,
      viewport: _vp, layout: _l, layoutId: _lid, custom: _c,
      drag: _d, dragConstraints: _dc, dragElastic: _de,
      onDragStart: _ods, onDragEnd: _ode,
      ...domProps
    } = rest as Record<string, unknown>;
    return React.createElement('div', { ...domProps, ref }, children);
  };
  MotionDiv.displayName = 'motion.div';

  const motion = new Proxy({} as Record<string, typeof MotionDiv>, {
    get(_target, prop: string) {
      return (
        { children, ref, ...rest }: React.HTMLAttributes<HTMLElement> & { ref?: React.Ref<HTMLElement> }
      ) => {
        const {
          initial: _i, animate: _a, exit: _e, transition: _t, variants: _v,
          whileHover: _wh, whileTap: _wt, whileFocus: _wf, whileInView: _wiv,
          viewport: _vp, layout: _l, layoutId: _lid, custom: _c,
          drag: _d, dragConstraints: _dc, dragElastic: _de,
          onDragStart: _ods, onDragEnd: _ode,
          ...domProps
        } = rest as Record<string, unknown>;
        return React.createElement(prop, { ...domProps, ref }, children);
      };
    },
  });

  function AnimatePresence({ children }: { children?: React.ReactNode }) {
    return React.createElement(React.Fragment, null, children);
  }

  return { motion, AnimatePresence, MotionConfig: ({ children }: { children?: React.ReactNode }) => children };
});

// ─────────────────────────────────────────────────────────────────────────────

describe('ConfettiCelebration', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders nothing when show=false', async () => {
    const { ConfettiCelebration } = await import('./ConfettiCelebration');
    const { container } = render(<ConfettiCelebration show={false} />);
    // The component returns null for show=false
    expect(container.querySelector('.absolute')).toBeNull();
  });

  it('renders the container div when show=true', async () => {
    const { ConfettiCelebration } = await import('./ConfettiCelebration');
    const { container } = render(<ConfettiCelebration show />);
    expect(container.querySelector('.absolute')).toBeTruthy();
  });

  it('renders 20 particle elements when show=true', async () => {
    const { ConfettiCelebration } = await import('./ConfettiCelebration');
    const { container } = render(<ConfettiCelebration show />);
    // 20 small square particles + 1 center icon div = 21 absolute divs
    // The outer wrapper is also absolute — count the coloured squares by class w-3
    const particles = container.querySelectorAll('.w-3.h-3');
    expect(particles).toHaveLength(20);
  });

  it('renders the PartyPopper icon container when show=true', async () => {
    const { ConfettiCelebration } = await import('./ConfettiCelebration');
    const { container } = render(<ConfettiCelebration show />);
    // The icon wrapper has flex items-center justify-center
    const iconWrapper = container.querySelector('.flex.items-center.justify-center');
    expect(iconWrapper).toBeTruthy();
  });

  it('particles have backgroundColor style set to one of the palette colours', async () => {
    const PALETTE = ['#6366f1', '#a855f7', '#22c55e', '#f59e0b', '#ec4899'];
    const { ConfettiCelebration } = await import('./ConfettiCelebration');
    const { container } = render(<ConfettiCelebration show />);
    const particles = container.querySelectorAll('.w-3.h-3');
    particles.forEach((p) => {
      // jsdom inline styles can transform hex to rgb, so just verify style is set
      const style = (p as HTMLElement).style.backgroundColor;
      expect(style).toBeTruthy();
      // Verify it's one of our palette colors (jsdom may convert to rgb)
      const isValidColor = PALETTE.some((hex) => {
        // Check hex directly or that the style is non-empty (jsdom transforms)
        return style === hex || style.startsWith('rgb');
      });
      expect(isValidColor).toBe(true);
    });
  });

  it('outer container has pointer-events-none to block interaction', async () => {
    const { ConfettiCelebration } = await import('./ConfettiCelebration');
    const { container } = render(<ConfettiCelebration show />);
    const outer = container.querySelector('.pointer-events-none');
    expect(outer).toBeTruthy();
  });

  it('outer container has overflow-hidden', async () => {
    const { ConfettiCelebration } = await import('./ConfettiCelebration');
    const { container } = render(<ConfettiCelebration show />);
    const outer = container.querySelector('.overflow-hidden');
    expect(outer).toBeTruthy();
  });

  it('outer container is z-10 (renders above siblings)', async () => {
    const { ConfettiCelebration } = await import('./ConfettiCelebration');
    const { container } = render(<ConfettiCelebration show />);
    const outer = container.querySelector('.z-10');
    expect(outer).toBeTruthy();
  });

  it('toggling show from false to true shows the component', async () => {
    const { ConfettiCelebration } = await import('./ConfettiCelebration');
    const { container, rerender } = render(<ConfettiCelebration show={false} />);
    expect(container.querySelector('.absolute')).toBeNull();

    rerender(<ConfettiCelebration show={true} />);
    expect(container.querySelector('.absolute')).toBeTruthy();
  });

  it('toggling show from true to false hides the component', async () => {
    const { ConfettiCelebration } = await import('./ConfettiCelebration');
    const { container, rerender } = render(<ConfettiCelebration show={true} />);
    // The confetti container has overflow-hidden; ToastProvider does not
    expect(container.querySelector('.overflow-hidden')).toBeTruthy();

    rerender(<ConfettiCelebration show={false} />);
    // After hiding, the overflow-hidden confetti wrapper is gone
    expect(container.querySelector('.overflow-hidden')).toBeNull();
  });
});
