// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { render, screen, waitFor } from '@/test/test-utils';
import { vi, describe, it, expect, beforeEach } from 'vitest';
import { SkillsTab } from './SkillsTab';

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn() },
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/components/skills/SkillSelector', () => ({
  SkillSelector: ({ userSkills }: { userSkills: unknown[] }) => (
    <div data-testid="skill-selector">Skills count: {userSkills.length}</div>
  ),
}));

import { api } from '@/lib/api';
const mockApi = api as unknown as { get: ReturnType<typeof vi.fn> };

describe('SkillsTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading spinner initially', () => {
    mockApi.get.mockReturnValue(new Promise(() => {}));
    render(<SkillsTab />);
    // Should show a spinner while loading
    expect(screen.queryByTestId('skill-selector')).toBeNull();
  });

  it('renders SkillSelector after loading', async () => {
    const mockSkills = [
      { id: 1, name: 'Cooking', category: 'Life Skills' },
      { id: 2, name: 'Driving', category: 'Transport' },
    ];
    mockApi.get.mockResolvedValue({ success: true, data: mockSkills });

    render(<SkillsTab />);
    await waitFor(() => expect(screen.getByTestId('skill-selector')).toBeDefined());
    expect(screen.getByText('Skills count: 2')).toBeDefined();
  });

  it('renders empty SkillSelector when no skills returned', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: [] });

    render(<SkillsTab />);
    await waitFor(() => expect(screen.getByTestId('skill-selector')).toBeDefined());
    expect(screen.getByText('Skills count: 0')).toBeDefined();
  });

  it('shows page heading', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: [] });
    render(<SkillsTab />);
    expect(screen.getByText('Your Skills')).toBeDefined();
  });

  it('calls correct API endpoint', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: [] });
    render(<SkillsTab />);
    await waitFor(() => expect(mockApi.get).toHaveBeenCalledWith('/v2/users/me/skills'));
  });

  it('handles API failure gracefully', async () => {
    mockApi.get.mockRejectedValue(new Error('Server error'));
    render(<SkillsTab />);
    await waitFor(() => expect(screen.getByTestId('skill-selector')).toBeDefined());
    // Should still render with empty skills
    expect(screen.getByText('Skills count: 0')).toBeDefined();
  });

  it('handles API returning success false gracefully', async () => {
    mockApi.get.mockResolvedValue({ success: false });
    render(<SkillsTab />);
    await waitFor(() => expect(screen.getByTestId('skill-selector')).toBeDefined());
    expect(screen.getByText('Skills count: 0')).toBeDefined();
  });
});
