// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';

const { mockAdmin } = vi.hoisted(() => ({
  mockAdmin: vi.fn(() => <div data-testid="admin-partnerships" />),
}));

vi.mock('@/admin/modules/federation/Partnerships', () => ({
  __esModule: true,
  default: mockAdmin,
}));

describe('PartnershipsPage (partner timebanks panel)', () => {
  it('frames the admin module in the panel shell with partners-namespace copy', async () => {
    const Component = (await import('./PartnershipsPage')).default;
    render(<Component />);

    expect(screen.getByRole('heading', { level: 1, name: 'Partnerships' })).toBeInTheDocument();
    expect(screen.getByTestId('admin-partnerships')).toBeInTheDocument();
    expect(mockAdmin).toHaveBeenCalledTimes(1);
  });

  it('scopes the duplicate-header suppression around the embedded module', async () => {
    const Component = (await import('./PartnershipsPage')).default;
    render(<Component />);

    const wrapper = screen.getByTestId('admin-partnerships').parentElement;
    expect(wrapper?.className).toContain(':first-child]:hidden');
  });
});
