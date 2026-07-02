// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';

const { mockAdmin } = vi.hoisted(() => ({
  mockAdmin: vi.fn(() => <div data-testid="admin-safeguarding-options" />),
}));

vi.mock('@/admin/modules/safeguarding/SafeguardingOptionsAdmin', () => ({
  __esModule: true,
  default: mockAdmin,
}));

describe('SafeguardingOptionsPage (broker)', () => {
  it('frames the admin module in the broker shell with broker-namespace copy', async () => {
    const Component = (await import('./SafeguardingOptionsPage')).default;
    render(<Component />);

    expect(screen.getByRole('heading', { level: 1, name: 'Safeguarding Options' })).toBeInTheDocument();
    expect(screen.getByText('Configure the safeguarding declaration options members can select.')).toBeInTheDocument();
    expect(screen.getByTestId('admin-safeguarding-options')).toBeInTheDocument();
    expect(mockAdmin).toHaveBeenCalledTimes(1);
  });

  it('scopes the duplicate-header suppression around the embedded module', async () => {
    const Component = (await import('./SafeguardingOptionsPage')).default;
    render(<Component />);

    const wrapper = screen.getByTestId('admin-safeguarding-options').parentElement;
    expect(wrapper?.className).toContain(':first-child]:hidden');
  });
});
