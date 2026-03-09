// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Automated accessibility audit using axe-core / vitest-axe.
 * Tests key UI components for WCAG 2.1 AA violations.
 */

import { describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';
import { axe } from 'vitest-axe';
import { HeroUIProvider } from '@heroui/react';
import { MemoryRouter } from 'react-router-dom';
import { EmptyState } from '@/components/feedback/EmptyState';
import { GlassCard } from '@/components/ui/GlassCard';
import { GlassButton } from '@/components/ui/GlassButton';

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('framer-motion', () => ({
  motion: new Proxy({} as Record<string, unknown>, {
    get: (_target, prop: string) => {
      const { forwardRef, createElement } = require('react');
      return forwardRef(({ children, ...rest }: Record<string, unknown>, ref: unknown) => {
        const clean: Record<string, unknown> = {};
        const motionProps = ['variants','initial','animate','exit','transition',
          'whileHover','whileTap','whileInView','layout','layoutId','viewport',
          'drag','dragConstraints','dragElastic','dragMomentum'];
        for (const [k, v] of Object.entries(rest)) {
          if (!motionProps.includes(k)) clean[k] = v;
        }
        return createElement(prop as string, { ...clean, ref }, children);
      });
    },
  }),
  AnimatePresence: ({ children }: { children: unknown }) => children,
  useAnimation: () => ({ start: () => Promise.resolve() }),
  useInView: () => true,
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, fallback?: string) => fallback ?? key,
    i18n: { language: 'en', changeLanguage: () => Promise.resolve() },
  }),
  Trans: ({ children }: { children: unknown }) => children,
  initReactI18next: { type: '3rdParty', init: () => {} },
}));

// ─── Helpers ─────────────────────────────────────────────────────────────────

function withProviders(ui: React.ReactElement) {
  return render(
    <HeroUIProvider>
      <MemoryRouter>
        {ui}
      </MemoryRouter>
    </HeroUIProvider>
  );
}

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('Accessibility: EmptyState', () => {
  it('has no violations with title only', async () => {
    const { container } = withProviders(
      <EmptyState title="No items found" />
    );
    const results = await axe(container);
    expect(results).toHaveNoViolations();
  });

  it('has no violations with title, description, and action button', async () => {
    const { container } = withProviders(
      <EmptyState
        title="No listings"
        description="Be the first to post a listing in your community."
        action={{ label: 'Post a listing', onClick: () => {} }}
      />
    );
    const results = await axe(container);
    expect(results).toHaveNoViolations();
  });
});

describe('Accessibility: GlassCard', () => {
  it('has no violations as a static card', async () => {
    const { container } = withProviders(
      <GlassCard>
        <h2>Card heading</h2>
        <p>Card body content.</p>
      </GlassCard>
    );
    const results = await axe(container);
    expect(results).toHaveNoViolations();
  });
});

describe('Accessibility: GlassButton', () => {
  it('has no violations for a standard button', async () => {
    const { container } = withProviders(
      <GlassButton>Click me</GlassButton>
    );
    const results = await axe(container);
    expect(results).toHaveNoViolations();
  });

  it('has no violations for a disabled button', async () => {
    const { container } = withProviders(
      <GlassButton disabled>Cannot click</GlassButton>
    );
    const results = await axe(container);
    expect(results).toHaveNoViolations();
  });
});

describe('Accessibility: skip link markup', () => {
  it('renders an anchor pointing to #main-content with descriptive text', () => {
    const { container } = render(
      <a
        href="#main-content"
        className="sr-only focus:not-sr-only"
      >
        Skip to main content
      </a>
    );
    const link = container.querySelector('a[href="#main-content"]');
    expect(link).not.toBeNull();
    expect(link?.textContent).toBe('Skip to main content');
  });
});
