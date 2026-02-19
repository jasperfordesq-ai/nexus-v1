// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { FeatureGate } from './FeatureGate';
import { useTenant } from '@/contexts';

vi.mock('@/contexts', () => ({
  useTenant: vi.fn(() => ({
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    isLoading: false,
    tenantPath: vi.fn((p: string) => `/test${p}`),
  })),
}));

describe('FeatureGate', () => {
  it('renders children when feature is enabled', () => {
    render(
      <FeatureGate feature="events">
        <div>Events Content</div>
      </FeatureGate>
    );
    expect(screen.getByText('Events Content')).toBeInTheDocument();
  });

  it('renders children when module is enabled', () => {
    render(
      <FeatureGate module="wallet">
        <div>Wallet Content</div>
      </FeatureGate>
    );
    expect(screen.getByText('Wallet Content')).toBeInTheDocument();
  });

  it('hides children when feature is disabled', () => {
    vi.mocked(useTenant).mockReturnValue({
      hasFeature: vi.fn(() => false),
      hasModule: vi.fn(() => true),
      isLoading: false,
      tenantPath: vi.fn((p: string) => `/test${p}`),
    } as any);

    render(
      <FeatureGate feature="events">
        <div>Events Content</div>
      </FeatureGate>
    );
    expect(screen.queryByText('Events Content')).not.toBeInTheDocument();
  });

  it('renders fallback when feature is disabled', () => {
    vi.mocked(useTenant).mockReturnValue({
      hasFeature: vi.fn(() => false),
      hasModule: vi.fn(() => true),
      isLoading: false,
      tenantPath: vi.fn((p: string) => `/test${p}`),
    } as any);

    render(
      <FeatureGate feature="events" fallback={<div>Feature unavailable</div>}>
        <div>Events Content</div>
      </FeatureGate>
    );
    expect(screen.queryByText('Events Content')).not.toBeInTheDocument();
    expect(screen.getByText('Feature unavailable')).toBeInTheDocument();
  });

  it('renders children while loading (assumes enabled)', () => {
    vi.mocked(useTenant).mockReturnValue({
      hasFeature: vi.fn(() => false),
      hasModule: vi.fn(() => false),
      isLoading: true,
      tenantPath: vi.fn((p: string) => `/test${p}`),
    } as any);

    render(
      <FeatureGate feature="events">
        <div>Events Content</div>
      </FeatureGate>
    );
    expect(screen.getByText('Events Content')).toBeInTheDocument();
  });

  it('renders children when neither feature nor module specified', () => {
    vi.mocked(useTenant).mockReturnValue({
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
      isLoading: false,
      tenantPath: vi.fn((p: string) => `/test${p}`),
    } as any);

    render(
      <FeatureGate>
        <div>Always Visible</div>
      </FeatureGate>
    );
    expect(screen.getByText('Always Visible')).toBeInTheDocument();
  });
});
