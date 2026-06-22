// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
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

describe('BrokerHelpPage (standalone route wrapper)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    render(<BrokerHelpPage />);
    expect(document.body).toBeInTheDocument();
  });

  it('renders the BrokerControlsHelp content (section tag)', () => {
    render(<BrokerHelpPage />);
    expect(document.querySelector('section')).toBeInTheDocument();
  });

  it('renders accordion items inside the wrapper (smoke test)', () => {
    render(<BrokerHelpPage />);
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThanOrEqual(10);
  });
});
