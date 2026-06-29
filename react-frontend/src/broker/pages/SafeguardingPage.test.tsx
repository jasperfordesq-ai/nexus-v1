// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';

const { mockSafeguardingDashboard } = vi.hoisted(() => ({
  mockSafeguardingDashboard: vi.fn(({ routeBase }: { routeBase?: string }) => (
    <div data-testid="shared-safeguarding-dashboard" data-route-base={routeBase} />
  )),
}));

vi.mock('@/admin/modules/safeguarding/SafeguardingDashboard', () => ({
  SafeguardingDashboard: mockSafeguardingDashboard,
}));

describe('SafeguardingPage (broker)', () => {
  it('reuses the full admin safeguarding dashboard with broker-scoped links', async () => {
    const mod = await import('./SafeguardingPage');
    const Component = mod.default;

    render(<Component />);

    expect(screen.getByTestId('shared-safeguarding-dashboard')).toHaveAttribute(
      'data-route-base',
      '/broker/safeguarding',
    );
    expect(mockSafeguardingDashboard).toHaveBeenCalledTimes(1);
  });
});
