// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── Mocks ───────────────────────────────────────────────────────────────────

vi.mock('@/contexts', () => createMockContexts());
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ── Tests ─────────────────────────────────────────────────────────────────────

import BrokerHelpPage, { BrokerControlsHelp } from './BrokerHelpPage';

describe('BrokerControlsHelp (presentational component)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    render(<BrokerControlsHelp />);
    expect(document.body).toBeInTheDocument();
  });

  it('renders a section element as the root', () => {
    render(<BrokerControlsHelp />);
    const section = document.querySelector('section');
    expect(section).toBeInTheDocument();
  });

  it('renders an Accordion with multiple items', () => {
    render(<BrokerControlsHelp />);
    // 10 accordion items each have a trigger button
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThanOrEqual(10);
  });

  it('renders the "What this module manages" accordion button (overview)', () => {
    render(<BrokerControlsHelp />);
    // English translation for help.overview.title = "What this module manages"
    const btn = screen
      .getAllByRole('button')
      .find((b) => b.textContent?.includes('What this module manages'));
    expect(btn).toBeTruthy();
  });

  it('renders the "Your daily workflow" accordion button (workflow)', () => {
    render(<BrokerControlsHelp />);
    // English translation for help.workflow.title = "Your daily workflow"
    const btn = screen
      .getAllByRole('button')
      .find((b) => b.textContent?.includes('Your daily workflow'));
    expect(btn).toBeTruthy();
  });

  it('renders the messages accordion item', () => {
    render(<BrokerControlsHelp />);
    const btn = screen
      .getAllByRole('button')
      .find((b) =>
        b.textContent?.toLowerCase().includes('message') ||
        b.getAttribute('aria-label')?.toLowerCase().includes('message')
      );
    expect(btn).toBeTruthy();
  });

  it('renders the vetting accordion item', () => {
    render(<BrokerControlsHelp />);
    const btn = screen
      .getAllByRole('button')
      .find((b) =>
        b.textContent?.toLowerCase().includes('vetting') ||
        b.getAttribute('aria-label')?.toLowerCase().includes('vetting')
      );
    expect(btn).toBeTruthy();
  });

  it('renders the alerts accordion item', () => {
    render(<BrokerControlsHelp />);
    const btn = screen
      .getAllByRole('button')
      .find((b) =>
        b.textContent?.toLowerCase().includes('alert') ||
        b.getAttribute('aria-label')?.toLowerCase().includes('alert')
      );
    expect(btn).toBeTruthy();
  });

  it('renders the legal accordion item', () => {
    render(<BrokerControlsHelp />);
    const btn = screen
      .getAllByRole('button')
      .find((b) =>
        b.textContent?.toLowerCase().includes('legal') ||
        b.getAttribute('aria-label')?.toLowerCase().includes('legal')
      );
    expect(btn).toBeTruthy();
  });

  it('renders the contacts accordion item', () => {
    render(<BrokerControlsHelp />);
    const btn = screen
      .getAllByRole('button')
      .find((b) =>
        b.textContent?.toLowerCase().includes('contact') ||
        b.getAttribute('aria-label')?.toLowerCase().includes('contact')
      );
    expect(btn).toBeTruthy();
  });

  it('renders the troubleshooting accordion item', () => {
    render(<BrokerControlsHelp />);
    const btn = screen
      .getAllByRole('button')
      .find((b) =>
        b.textContent?.toLowerCase().includes('troubleshoot') ||
        b.getAttribute('aria-label')?.toLowerCase().includes('troubleshoot')
      );
    expect(btn).toBeTruthy();
  });
});

describe('BrokerHelpPage (standalone searchable help center)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Tests share the real BrowserRouter history — reset the deep-linkable
    // ?q= param so one test's search can't leak into the next.
    window.history.replaceState({}, '', '/');
  });

  it('renders without crashing', () => {
    render(<BrokerHelpPage />);
    expect(document.body).toBeInTheDocument();
  });

  it('renders the broker shell header with the help title and description', () => {
    render(<BrokerHelpPage />);
    expect(
      screen.getByRole('heading', { level: 1, name: 'Guidance & reference' })
    ).toBeInTheDocument();
    expect(
      screen.getByText(/How the brokering and safeguarding system works/)
    ).toBeInTheDocument();
  });

  it('renders all help section accordion triggers (smoke test)', () => {
    render(<BrokerHelpPage />);
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThanOrEqual(10);
    expect(
      buttons.find((b) => b.textContent?.includes('What this module manages'))
    ).toBeTruthy();
  });

  it('renders the search input with placeholder and topic count', () => {
    render(<BrokerHelpPage />);
    const input = screen.getByLabelText('Search help topics');
    expect(input).toBeInTheDocument();
    expect(input).toHaveAttribute('placeholder', 'Search help topics...');
    expect(screen.getByText('Showing 10 of 10 topics')).toBeInTheDocument();
  });

  it('filters sections client-side by body copy, case-insensitively', () => {
    render(<BrokerHelpPage />);
    // "Tusla" only appears in the alerts section's escalation guidance.
    fireEvent.change(screen.getByLabelText('Search help topics'), {
      target: { value: 'tusla' },
    });

    expect(screen.getByText('Showing 1 of 10 topics')).toBeInTheDocument();
    expect(
      screen
        .getAllByRole('button')
        .find((b) => b.textContent?.includes('Safeguarding alerts & escalation'))
    ).toBeTruthy();
    expect(screen.queryByText('What this module manages')).not.toBeInTheDocument();
  });

  it('syncs the search query to the URL (?q=) for deep-linking', () => {
    render(<BrokerHelpPage />);
    fireEvent.change(screen.getByLabelText('Search help topics'), {
      target: { value: 'Tusla' },
    });
    expect(window.location.search).toContain('q=Tusla');
  });

  it('honours a deep-linked ?q= filter on first render', () => {
    window.history.replaceState({}, '', '/?q=tusla');
    render(<BrokerHelpPage />);
    expect(screen.getByText('Showing 1 of 10 topics')).toBeInTheDocument();
    expect(screen.getByLabelText('Search help topics')).toHaveValue('tusla');
  });

  it('shows an empty state with a working clear action when nothing matches', async () => {
    const user = userEvent.setup();
    render(<BrokerHelpPage />);

    fireEvent.change(screen.getByLabelText('Search help topics'), {
      target: { value: 'zzz-definitely-not-a-help-topic' },
    });

    expect(screen.getByText('No topics match your search')).toBeInTheDocument();
    expect(
      screen.getByText('Try a different keyword, or clear the search to browse every topic.')
    ).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Clear search' }));

    expect(screen.getByText('Showing 10 of 10 topics')).toBeInTheDocument();
    expect(screen.queryByText('No topics match your search')).not.toBeInTheDocument();
    expect(window.location.search).toBe('');
  });
});
