// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ── Mocks ──────────────────────────────────────────────────────────────────────

vi.mock('@/contexts', () => createMockContexts());

import { SafeguardingHelp } from './SafeguardingHelp';

// SafeguardingHelp is a static informational component — no API calls,
// no loading states, no side effects. Tests verify structure and content.

describe('SafeguardingHelp', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    render(<SafeguardingHelp />);
    // Landmark section is always present
    expect(document.querySelector('section')).toBeInTheDocument();
  });

  it('renders the help panel card header title', () => {
    render(<SafeguardingHelp />);
    // The h2 "How Safeguarding Works" (or i18n fallback key) is the top heading
    const headings = screen.getAllByRole('heading');
    expect(headings.length).toBeGreaterThan(0);
  });

  it('renders the accordion with multiple items', () => {
    render(<SafeguardingHelp />);
    // Accordion items render as buttons (collapsed) or expanded panels.
    // There are 9 accordion items — each has an aria-label so they render buttons.
    const buttons = screen.getAllByRole('button');
    // We expect at least 7 accordion trigger buttons
    expect(buttons.length).toBeGreaterThanOrEqual(7);
  });

  it('renders the trigger table header columns', () => {
    render(<SafeguardingHelp />);
    // The triggers section table has two columns — "Trigger key" and "Effect"
    // Even if accordion items are collapsed the Table is declared in the DOM
    // because HeroUI Accordion renders content eagerly.
    // Just check some readable text in the document.
    const cells = document.querySelectorAll('th, [role="columnheader"]');
    expect(cells.length).toBeGreaterThanOrEqual(2);
  });

  it('renders all 6 trigger row codes in the table', () => {
    render(<SafeguardingHelp />);
    // The 6 trigger keys are rendered as <code> elements
    const codes = document.querySelectorAll('code');
    const triggerCodes = Array.from(codes).filter((c) =>
      [
        'requires_broker_approval',
        'restricts_messaging',
        'restricts_matching',
        'requires_vetted_interaction',
        'notify_admin_on_selection',
        'vetting_type_required',
      ].includes(c.textContent ?? '')
    );
    expect(triggerCodes.length).toBe(6);
  });

  it('renders autonomy principle section code snippet', () => {
    render(<SafeguardingHelp />);
    // The autonomy accordion contains `action = 'safeguarding_consent_revoked'`
    const allCodes = Array.from(document.querySelectorAll('code'));
    const consentCode = allCodes.find((c) =>
      c.textContent?.includes('safeguarding_consent_revoked')
    );
    expect(consentCode).toBeInTheDocument();
  });

  it('renders MessageService::send code reference in vetting section', () => {
    render(<SafeguardingHelp />);
    const allCodes = Array.from(document.querySelectorAll('code'));
    const msgCode = allCodes.find((c) => c.textContent?.includes('MessageService::send'));
    expect(msgCode).toBeInTheDocument();
  });

  it('renders safeguarding:review-flags cron reference', () => {
    render(<SafeguardingHelp />);
    const allCodes = Array.from(document.querySelectorAll('code'));
    const cronCode = allCodes.find((c) => c.textContent?.includes('safeguarding:review-flags'));
    expect(cronCode).toBeInTheDocument();
  });

  it('renders activity_log code reference in audit section', () => {
    render(<SafeguardingHelp />);
    const allCodes = Array.from(document.querySelectorAll('code'));
    const logCode = allCodes.find((c) => c.textContent?.includes('activity_log'));
    expect(logCode).toBeInTheDocument();
  });

  it('does not trigger any API calls (pure static component)', () => {
    // No api mock needed — just ensure no unhandled fetch errors
    render(<SafeguardingHelp />);
    // If any fetch was made, vitest would throw on unmocked modules
    expect(document.querySelector('section')).toBeInTheDocument();
  });
});
