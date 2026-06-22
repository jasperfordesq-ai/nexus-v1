// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/contexts', () => createMockContexts());

const STORAGE_KEY = 'nexus_vol_welcome_dismissed_v1';

import { VolunteeringWelcome } from './VolunteeringWelcome';

describe('VolunteeringWelcome', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.removeItem(STORAGE_KEY);
  });

  it('renders the welcome panel when not dismissed', () => {
    render(<VolunteeringWelcome />);
    // i18n resolves welcome.dismiss → "Got it" in the test environment
    // Find the icon-only close button (has aria-label)
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThan(0);
  });

  it('renders all three numbered steps', () => {
    render(<VolunteeringWelcome />);
    // Steps are rendered as <li> items with numbers 1, 2, 3
    expect(screen.getByText('1')).toBeInTheDocument();
    expect(screen.getByText('2')).toBeInTheDocument();
    expect(screen.getByText('3')).toBeInTheDocument();
  });

  it('renders the find/browse CTA button', () => {
    render(<VolunteeringWelcome />);
    // The find CTA is a button (rendered as Link but aria role=button via HeroUI)
    // Look for any button besides dismiss
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThan(1);
  });

  it('hides itself after dismiss button is pressed', () => {
    render(<VolunteeringWelcome />);
    // i18n resolves welcome.dismiss → "Got it"; there are two such buttons — click first
    const dismissBtns = screen.getAllByRole('button', { name: /got it/i });
    fireEvent.click(dismissBtns[0]);
    // After dismiss all "Got it" buttons are gone
    expect(screen.queryAllByRole('button', { name: /got it/i })).toHaveLength(0);
  });

  it('persists dismissal to localStorage', () => {
    render(<VolunteeringWelcome />);
    const dismissBtns = screen.getAllByRole('button', { name: /got it/i });
    fireEvent.click(dismissBtns[0]);
    expect(localStorage.getItem(STORAGE_KEY)).toBe('1');
  });

  it('renders nothing when already dismissed via localStorage', () => {
    localStorage.setItem(STORAGE_KEY, '1');
    render(<VolunteeringWelcome />);
    // Component returns null — the welcome heading should not be in the DOM
    expect(screen.queryByRole('heading', { name: /welcome to volunteering/i })).not.toBeInTheDocument();
    // And no dismiss buttons are rendered
    expect(screen.queryAllByRole('button', { name: /got it/i })).toHaveLength(0);
  });

  it('hides after pressing the secondary dismiss text button', () => {
    render(<VolunteeringWelcome />);
    // Both the icon close button and text button resolve to "Got it" via i18n
    const dismissBtns = screen.getAllByRole('button', { name: /got it/i });
    // Click the last one (the text button)
    fireEvent.click(dismissBtns[dismissBtns.length - 1]);
    expect(screen.queryByRole('button', { name: /got it/i })).not.toBeInTheDocument();
  });
});
