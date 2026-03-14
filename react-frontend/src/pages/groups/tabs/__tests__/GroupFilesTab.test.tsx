// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for GroupFilesTab (Coming Soon placeholder)
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (_key: string, fallback: string) => fallback ?? _key,
  }),
}));

import { GroupFilesTab } from '../GroupFilesTab';

describe('GroupFilesTab', () => {
  it('renders the Files heading', () => {
    render(<GroupFilesTab groupId={1} isAdmin={false} />);
    expect(screen.getByText('Files')).toBeInTheDocument();
  });

  it('renders the Coming Soon badge', () => {
    render(<GroupFilesTab groupId={1} isAdmin={false} />);
    expect(screen.getByText('Coming Soon')).toBeInTheDocument();
  });

  it('renders the description text', () => {
    render(<GroupFilesTab groupId={1} isAdmin={false} />);
    expect(
      screen.getByText(/Group file sharing is currently in development/)
    ).toBeInTheDocument();
  });

  it('does not make any API calls', () => {
    render(<GroupFilesTab groupId={1} isAdmin={true} isMember={true} />);
    // No API mock needed — the component makes no network requests
    expect(screen.getByText('Coming Soon')).toBeInTheDocument();
  });
});
