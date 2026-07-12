// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';

vi.mock('../components/ScheduledPostPanel', () => ({
  ScheduledPostPanel: () => <div>Scheduled panel</div>,
}));
vi.mock('../components/WebhookConfigPanel', () => ({
  WebhookConfigPanel: () => <div>Webhook panel</div>,
}));
vi.mock('../components/WelcomeConfigPanel', () => ({
  WelcomeConfigPanel: () => <div>Welcome panel</div>,
}));
vi.mock('../components/GroupDataExportPanel', () => ({
  GroupDataExportPanel: () => <div>Export panel</div>,
}));

import { GroupAutomationTab } from './GroupAutomationTab';

describe('GroupAutomationTab', () => {
  it('mounts every retained admin capability in one reachable section', () => {
    render(<GroupAutomationTab groupId={4} isAdmin />);
    expect(screen.getByRole('heading', { name: /automation/i })).toBeInTheDocument();
    expect(screen.getByText('Scheduled panel')).toBeInTheDocument();
    expect(screen.getByText('Webhook panel')).toBeInTheDocument();
    expect(screen.getByText('Welcome panel')).toBeInTheDocument();
    expect(screen.getByText('Export panel')).toBeInTheDocument();
  });

  it('does not expose management controls to ordinary members', () => {
    const { container } = render(<GroupAutomationTab groupId={4} isAdmin={false} />);
    expect(container).toBeEmptyDOMElement();
  });
});
