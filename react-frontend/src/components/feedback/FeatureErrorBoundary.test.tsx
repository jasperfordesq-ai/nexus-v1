// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { FeatureErrorBoundary } from './FeatureErrorBoundary';

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

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

function ThrowingComponent() {
  throw new Error('Feature error');
}

const originalConsoleError = console.error;

describe('FeatureErrorBoundary', () => {
  beforeEach(() => {
    console.error = vi.fn();
  });

  afterEach(() => {
    console.error = originalConsoleError;
  });

  it('renders children when no error', () => {
    render(
      <FeatureErrorBoundary featureName="Dashboard">
        <div>Feature Content</div>
      </FeatureErrorBoundary>
    );
    expect(screen.getByText('Feature Content')).toBeInTheDocument();
  });

  it('renders error UI when child throws', () => {
    render(
      <FeatureErrorBoundary featureName="Dashboard">
        <ThrowingComponent />
      </FeatureErrorBoundary>
    );
    expect(screen.getByText('Something went wrong')).toBeInTheDocument();
    expect(screen.getByText(/couldn't load Dashboard/i)).toBeInTheDocument();
  });

  it('renders custom fallback when provided', () => {
    render(
      <FeatureErrorBoundary featureName="Widget" fallback={<div>Widget unavailable</div>}>
        <ThrowingComponent />
      </FeatureErrorBoundary>
    );
    expect(screen.getByText('Widget unavailable')).toBeInTheDocument();
  });

  it('shows Try Again button in error state', () => {
    render(
      <FeatureErrorBoundary featureName="Feed">
        <ThrowingComponent />
      </FeatureErrorBoundary>
    );
    expect(screen.getByText('Try Again')).toBeInTheDocument();
  });
});
