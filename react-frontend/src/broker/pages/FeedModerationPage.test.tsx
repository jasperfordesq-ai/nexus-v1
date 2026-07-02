// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';

const { mockAdmin } = vi.hoisted(() => ({
  mockAdmin: vi.fn(() => <div data-testid="admin-feed-moderation" />),
}));

vi.mock('@/admin/modules/moderation/FeedModeration', () => ({
  __esModule: true,
  default: mockAdmin,
}));

describe('FeedModerationPage (broker)', () => {
  it('frames the admin module in the broker shell with broker-namespace copy', async () => {
    const Component = (await import('./FeedModerationPage')).default;
    render(<Component />);

    expect(screen.getByRole('heading', { level: 1, name: 'Feed Moderation' })).toBeInTheDocument();
    expect(screen.getByText('Review and moderate posts, listings, events, and other feed content.')).toBeInTheDocument();
    expect(screen.getByTestId('admin-feed-moderation')).toBeInTheDocument();
    expect(mockAdmin).toHaveBeenCalledTimes(1);
  });

  it('scopes the duplicate-header suppression around the embedded module', async () => {
    const Component = (await import('./FeedModerationPage')).default;
    render(<Component />);

    const wrapper = screen.getByTestId('admin-feed-moderation').parentElement;
    expect(wrapper?.className).toContain(':first-child]:hidden');
  });
});
