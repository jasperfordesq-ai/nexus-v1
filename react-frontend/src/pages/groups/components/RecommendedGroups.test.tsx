// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for RecommendedGroups
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

const mockApiGet = vi.fn();
const mockApiPost = vi.fn().mockResolvedValue({ success: true });

vi.mock('@/lib/api', () => ({
  api: {
    get: (...args: unknown[]) => mockApiGet(...args),
    post: (...args: unknown[]) => mockApiPost(...args),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({
  resolveThumbnailUrl: (url: string | null) => url || '',
}));

let isAuthenticated = true;
const stableToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
const stableTenant = {
  tenantPath: (p: string) => `/test${p}`,
};

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({ isAuthenticated })),
  useToast: vi.fn(() => stableToast),
  useTenant: vi.fn(() => stableTenant),
}));

vi.mock('@/components/ui', async () => (await import('@/test/uiMock')).uiMock);

import { RecommendedGroups } from './RecommendedGroups';

describe('RecommendedGroups', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    isAuthenticated = true;
  });

  it('renders nothing when the user is not authenticated', () => {
    isAuthenticated = false;
    const { container } = render(<RecommendedGroups />);
    expect(container).toBeEmptyDOMElement();
    expect(mockApiGet).not.toHaveBeenCalled();
  });

  it('renders nothing when the API call fails', async () => {
    mockApiGet.mockResolvedValue({ success: false });
    const { container } = render(<RecommendedGroups />);
    await waitFor(() => {
      expect(mockApiGet).toHaveBeenCalled();
    });
    expect(container).toBeEmptyDOMElement();
  });

  it('renders nothing when there are no group matches', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: { matches: [] } });
    const { container } = render(<RecommendedGroups />);
    await waitFor(() => {
      expect(mockApiGet).toHaveBeenCalled();
    });
    expect(container).toBeEmptyDOMElement();
  });

  it('renders up to 3 recommended groups with a score chip', async () => {
    mockApiGet.mockResolvedValue({
      success: true,
      data: {
        matches: [
          { module: 'group', group_id: 1, title: 'Garden Crew', match_score: 82, match_reasons: ['Shared interest in gardening'] },
          { module: 'group', group_id: 2, title: 'Book Club', match_score: 71 },
        ],
      },
    });
    render(<RecommendedGroups />);
    await waitFor(() => {
      expect(screen.getByText('Garden Crew')).toBeInTheDocument();
    });
    expect(screen.getByText('Book Club')).toBeInTheDocument();
  });
});
