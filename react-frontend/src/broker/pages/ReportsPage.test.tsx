// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';

const { mockAdmin } = vi.hoisted(() => ({
  mockAdmin: vi.fn(() => <div data-testid="admin-reports-management" />),
}));

vi.mock('@/admin/modules/moderation/ReportsManagement', () => ({
  __esModule: true,
  default: mockAdmin,
}));

describe('ReportsPage (broker)', () => {
  it('frames the admin module in the broker shell with broker-namespace copy', async () => {
    const Component = (await import('./ReportsPage')).default;
    render(<Component />);

    expect(screen.getByRole('heading', { level: 1, name: 'Reports' })).toBeInTheDocument();
    expect(screen.getByText('Triage and resolve member reports about content and other members.')).toBeInTheDocument();
    expect(screen.getByTestId('admin-reports-management')).toBeInTheDocument();
    expect(mockAdmin).toHaveBeenCalledTimes(1);
  });

  it('scopes the duplicate-header suppression around the embedded module', async () => {
    const Component = (await import('./ReportsPage')).default;
    render(<Component />);

    const wrapper = screen.getByTestId('admin-reports-management').parentElement;
    expect(wrapper?.className).toContain(':first-child]:hidden');
  });
});
