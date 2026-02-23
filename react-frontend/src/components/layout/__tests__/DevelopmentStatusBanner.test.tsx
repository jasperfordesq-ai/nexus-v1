// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for DevelopmentStatusBanner component
 * Verifies rendering, accessibility attributes, and "Read more" link
 */

import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { DevelopmentStatusBanner } from '../DevelopmentStatusBanner';

function renderBanner() {
  return render(
    <MemoryRouter>
      <DevelopmentStatusBanner />
    </MemoryRouter>
  );
}

describe('DevelopmentStatusBanner', () => {
  it('renders the release stage label', () => {
    renderBanner();
    expect(screen.getByText(/Release Candidate \(RC\)/i)).toBeTruthy();
  });

  it('renders the stage summary text', () => {
    renderBanner();
    expect(screen.getByText(/final hardening/i)).toBeTruthy();
  });

  it('renders a "Read more" link', () => {
    renderBanner();
    const link = screen.getByRole('link', { name: /read more/i });
    expect(link).toBeTruthy();
    expect(link.getAttribute('href')).toContain('development-status');
  });

  it('has role="region" for accessibility', () => {
    renderBanner();
    const region = screen.getByRole('region', { name: /development status/i });
    expect(region).toBeTruthy();
  });
});
