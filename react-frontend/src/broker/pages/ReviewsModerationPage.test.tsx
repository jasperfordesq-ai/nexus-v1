// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';

const { mockAdmin } = vi.hoisted(() => ({
  mockAdmin: vi.fn(() => <div data-testid="admin-reviews-moderation" />),
}));

vi.mock('@/admin/modules/moderation/ReviewsModeration', () => ({
  __esModule: true,
  default: mockAdmin,
}));

describe('ReviewsModerationPage (broker)', () => {
  it('frames the admin module in the broker shell with broker-namespace copy', async () => {
    const Component = (await import('./ReviewsModerationPage')).default;
    render(<Component />);

    expect(screen.getByRole('heading', { level: 1, name: 'Reviews Moderation' })).toBeInTheDocument();
    expect(screen.getByText('Review, flag, and moderate member reviews.')).toBeInTheDocument();
    expect(screen.getByTestId('admin-reviews-moderation')).toBeInTheDocument();
    expect(mockAdmin).toHaveBeenCalledTimes(1);
  });

  it('scopes the duplicate-header suppression around the embedded module', async () => {
    const Component = (await import('./ReviewsModerationPage')).default;
    render(<Component />);

    const wrapper = screen.getByTestId('admin-reviews-moderation').parentElement;
    expect(wrapper?.className).toContain(':first-child]:hidden');
  });
});
