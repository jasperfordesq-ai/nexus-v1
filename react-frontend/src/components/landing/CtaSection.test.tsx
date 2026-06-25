// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock @/lib/motion — strip motion-specific props and passthrough ───────────
vi.mock('@/lib/motion', () => {
  const React = require('react');

  const MotionDiv = (
    { children, ref, ...rest }: React.HTMLAttributes<HTMLDivElement> & { ref?: React.Ref<HTMLDivElement> }
  ) => {
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

  function MotionConfig({ children }: { children?: React.ReactNode }) {
    return React.createElement(React.Fragment, null, children);
  }

  return { motion, AnimatePresence, MotionConfig, default: motion };
});

// ─── Stub HeroUI Button to avoid React Aria jsdom issues ─────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<Record<string, unknown>>();
  return {
    ...orig,
    Button: ({
      children,
      as: As,
      to,
      endContent,
      ...rest
    }: {
      children: React.ReactNode;
      as?: React.ElementType;
      to?: string;
      endContent?: React.ReactNode;
      [key: string]: unknown;
    }) => {
      if (As) {
        return <As to={to} {...rest}>{children}{endContent}</As>;
      }
      return <button {...(rest as React.ButtonHTMLAttributes<HTMLButtonElement>)}>{children}{endContent}</button>;
    },
  };
});

// ─── Mock react-i18next ───────────────────────────────────────────────────────
vi.mock('react-i18next', async (importOriginal) => {
  const orig = await importOriginal<typeof import('react-i18next')>();
  return {
    ...orig,
    useTranslation: (_ns?: string) => ({
      t: (key: string) => {
        const map: Record<string, string> = {
          'home.cta_section.title': 'Join the Timebank Today',
          'home.cta_section.description': 'Connect with your community through time credits.',
          'home.cta_section.button': 'Get Started',
        };
        return map[key] ?? key;
      },
      i18n: { language: 'en' },
    }),
  };
});

// ─── Auth mock factory — helps avoid inline arrows in dep arrays ──────────────
const { mockAuth } = vi.hoisted(() => ({
  mockAuth: {
    isAuthenticated: false as boolean,
    user: null as null | { id: number; name: string },
    login: vi.fn(),
    logout: vi.fn(),
    register: vi.fn(),
    updateUser: vi.fn(),
    refreshUser: vi.fn(),
    status: 'idle' as const,
    error: null,
  },
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => mockAuth,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

// ─────────────────────────────────────────────────────────────────────────────

describe('CtaSection', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockAuth.isAuthenticated = false;
  });

  it('renders the section for unauthenticated visitors', async () => {
    const { CtaSection } = await import('./CtaSection');
    render(<CtaSection />);
    // The section's accessible name comes from the h2 text via aria-labelledby
    expect(screen.getByRole('region', { name: /Join the Timebank Today/i })).toBeInTheDocument();
  });

  it('renders the default translated title', async () => {
    const { CtaSection } = await import('./CtaSection');
    render(<CtaSection />);
    expect(screen.getByText('Join the Timebank Today')).toBeInTheDocument();
  });

  it('renders the default translated description', async () => {
    const { CtaSection } = await import('./CtaSection');
    render(<CtaSection />);
    expect(screen.getByText('Connect with your community through time credits.')).toBeInTheDocument();
  });

  it('renders the default translated button text', async () => {
    const { CtaSection } = await import('./CtaSection');
    render(<CtaSection />);
    expect(screen.getByText(/Get Started/)).toBeInTheDocument();
  });

  it('returns null when user is authenticated', async () => {
    mockAuth.isAuthenticated = true;
    const { CtaSection } = await import('./CtaSection');
    const { container } = render(<CtaSection />);
    expect(container.querySelector('section')).toBeNull();
  });

  it('uses custom content.title when provided', async () => {
    const { CtaSection } = await import('./CtaSection');
    render(<CtaSection content={{ title: 'Custom CTA Title' }} />);
    expect(screen.getByText('Custom CTA Title')).toBeInTheDocument();
  });

  it('uses custom content.description when provided', async () => {
    const { CtaSection } = await import('./CtaSection');
    render(<CtaSection content={{ description: 'A custom description here.' }} />);
    expect(screen.getByText('A custom description here.')).toBeInTheDocument();
  });

  it('uses custom content.button_text when provided', async () => {
    const { CtaSection } = await import('./CtaSection');
    render(<CtaSection content={{ button_text: 'Sign Up Now' }} />);
    expect(screen.getByText(/Sign Up Now/)).toBeInTheDocument();
  });

  it('button links to tenant register path by default', async () => {
    const { CtaSection } = await import('./CtaSection');
    render(<CtaSection />);
    const link = screen.getByRole('link');
    expect((link as HTMLAnchorElement).href).toContain('/test/register');
  });

  it('button links to custom button_link when provided via content', async () => {
    const { CtaSection } = await import('./CtaSection');
    render(<CtaSection content={{ button_link: '/join' }} />);
    const link = screen.getByRole('link');
    expect((link as HTMLAnchorElement).href).toContain('/test/join');
  });

  it('h2 has id="cta-heading" for aria-labelledby', async () => {
    const { CtaSection } = await import('./CtaSection');
    render(<CtaSection />);
    const heading = screen.getByRole('heading', { level: 2 });
    expect(heading).toHaveAttribute('id', 'cta-heading');
  });

  it('section has aria-labelledby pointing at the heading', async () => {
    const { CtaSection } = await import('./CtaSection');
    render(<CtaSection />);
    const section = screen.getByRole('region');
    expect(section).toHaveAttribute('aria-labelledby', 'cta-heading');
  });
});
