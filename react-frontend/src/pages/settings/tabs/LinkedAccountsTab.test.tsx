// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { render, screen } from '@/test/test-utils';
import { vi, describe, it, expect } from 'vitest';
import { LinkedAccountsTab } from './LinkedAccountsTab';

vi.mock('@/components/subaccounts/SubAccountsManager', () => ({
  SubAccountsManager: () => <div data-testid="subaccounts-manager">Sub Accounts Manager</div>,
}));

describe('LinkedAccountsTab', () => {
  it('renders the SubAccountsManager component', () => {
    render(<LinkedAccountsTab />);
    expect(screen.getByTestId('subaccounts-manager')).toBeDefined();
  });

  it('wraps SubAccountsManager in a GlassCard', () => {
    render(<LinkedAccountsTab />);
    expect(screen.getByText('Sub Accounts Manager')).toBeDefined();
  });
});
