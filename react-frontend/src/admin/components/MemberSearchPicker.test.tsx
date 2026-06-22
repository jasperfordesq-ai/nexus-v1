// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── Mock adminApi ──────────────────────────────────────────────────────────────
vi.mock('../api/adminApi', () => ({
  adminUsers: {
    list: vi.fn(),
    get: vi.fn(),
  },
}));

vi.mock('@/contexts', () => createMockContexts());

import { adminUsers } from '../api/adminApi';
import { MemberSearchPicker } from './MemberSearchPicker';

const MOCK_MEMBERS = [
  { id: 1, name: 'Alice Smith', email: 'alice@example.com', avatar_url: null },
  { id: 2, name: 'Bob Jones', email: 'bob@example.com', avatar_url: null },
];

const DEFAULT_PROPS = {
  value: '',
  onValueChange: vi.fn(),
  onSelectedMemberChange: vi.fn(),
  label: 'Pick a member',
  placeholder: 'Search members…',
  noResultsText: 'No members found',
  clearText: 'Clear',
};

describe('MemberSearchPicker', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the search input with the given label', () => {
    render(<MemberSearchPicker {...DEFAULT_PROPS} />);
    expect(screen.getByLabelText(/Pick a member/i)).toBeInTheDocument();
  });

  it('shows no dropdown before typing', () => {
    render(<MemberSearchPicker {...DEFAULT_PROPS} />);
    expect(screen.queryByText('Alice Smith')).not.toBeInTheDocument();
  });

  it('shows noResultsText when query has ≥2 chars but API returns empty', async () => {
    vi.mocked(adminUsers.list).mockResolvedValue({ success: true, data: [] });
    render(<MemberSearchPicker {...DEFAULT_PROPS} />);
    const input = screen.getByLabelText(/Pick a member/i);
    fireEvent.change(input, { target: { value: 'zz' } });
    await waitFor(() => {
      expect(screen.getByText('No members found')).toBeInTheDocument();
      expect(adminUsers.list).toHaveBeenCalledWith(
        expect.objectContaining({ search: 'zz' })
      );
    });
  });

  it('renders search results in a dropdown after debounce', async () => {
    vi.mocked(adminUsers.list).mockResolvedValue({
      success: true,
      data: MOCK_MEMBERS,
    });
    render(<MemberSearchPicker {...DEFAULT_PROPS} />);
    const input = screen.getByLabelText(/Pick a member/i);
    fireEvent.change(input, { target: { value: 'Ali' } });
    await waitFor(() => {
      expect(screen.getByText('Alice Smith')).toBeInTheDocument();
    });
    expect(screen.getByText('Bob Jones')).toBeInTheDocument();
  });

  it('fires onSelectedMemberChange and onValueChange when a result is selected', async () => {
    const onValueChange = vi.fn();
    const onSelectedMemberChange = vi.fn();
    vi.mocked(adminUsers.list).mockResolvedValue({
      success: true,
      data: MOCK_MEMBERS,
    });
    render(
      <MemberSearchPicker
        {...DEFAULT_PROPS}
        onValueChange={onValueChange}
        onSelectedMemberChange={onSelectedMemberChange}
      />
    );
    const input = screen.getByLabelText(/Pick a member/i);
    fireEvent.change(input, { target: { value: 'Ali' } });
    await waitFor(() => {
      expect(screen.getByText('Alice Smith')).toBeInTheDocument();
    });
    // Click the first result button
    const resultBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.includes('Alice Smith')
    );
    expect(resultBtn).toBeInTheDocument();
    await userEvent.click(resultBtn!);
    expect(onValueChange).toHaveBeenCalledWith('1');
    expect(onSelectedMemberChange).toHaveBeenCalledWith(
      expect.objectContaining({ id: 1, name: 'Alice Smith' })
    );
  });

  it('renders the selected member card when selectedMember is provided', () => {
    render(
      <MemberSearchPicker
        {...DEFAULT_PROPS}
        value="1"
        selectedMember={{ id: 1, name: 'Alice Smith', email: 'alice@example.com' }}
      />
    );
    expect(screen.getByText('Alice Smith')).toBeInTheDocument();
    expect(screen.getByText('alice@example.com')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Clear/i })).toBeInTheDocument();
  });

  it('fires onSelectedMemberChange(null) and onValueChange("") when Clear is pressed', async () => {
    const onValueChange = vi.fn();
    const onSelectedMemberChange = vi.fn();
    render(
      <MemberSearchPicker
        {...DEFAULT_PROPS}
        value="1"
        selectedMember={{ id: 1, name: 'Alice Smith', email: 'alice@example.com' }}
        onValueChange={onValueChange}
        onSelectedMemberChange={onSelectedMemberChange}
      />
    );
    await userEvent.click(screen.getByRole('button', { name: /Clear/i }));
    expect(onSelectedMemberChange).toHaveBeenCalledWith(null);
    expect(onValueChange).toHaveBeenCalledWith('');
  });

  it('does not call api when query is shorter than 2 chars', async () => {
    render(<MemberSearchPicker {...DEFAULT_PROPS} />);
    const input = screen.getByLabelText(/Pick a member/i);
    fireEvent.change(input, { target: { value: 'a' } });
    // Wait a bit past debounce time
    await new Promise((r) => setTimeout(r, 400));
    expect(adminUsers.list).not.toHaveBeenCalled();
  });

  it('does not show dropdown when API returns success:false', async () => {
    vi.mocked(adminUsers.list).mockResolvedValue({ success: false, data: null });
    render(<MemberSearchPicker {...DEFAULT_PROPS} />);
    const input = screen.getByLabelText(/Pick a member/i);
    fireEvent.change(input, { target: { value: 'Ali' } });
    await waitFor(() => {
      expect(adminUsers.list).toHaveBeenCalled();
    });
    expect(screen.queryByText('Alice Smith')).not.toBeInTheDocument();
  });
});
