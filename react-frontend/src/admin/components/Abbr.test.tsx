// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/contexts', () => createMockContexts());

import { Abbr, ABBR_TERMS } from './Abbr';

/** <abbr> has no implicit ARIA role — query by tag name. */
function getAbbrEl() {
  return document.querySelector('abbr');
}

describe('Abbr', () => {
  it('renders an <abbr> element for a known term', () => {
    render(<Abbr term="CHF" />);
    expect(getAbbrEl()).not.toBeNull();
  });

  it('uses the term key as default children', () => {
    render(<Abbr term="GDPR" />);
    expect(screen.getByText('GDPR')).toBeInTheDocument();
  });

  it('renders custom children when provided', () => {
    render(<Abbr term="CHF">CHF 35/hr</Abbr>);
    expect(screen.getByText('CHF 35/hr')).toBeInTheDocument();
  });

  it('sets title attribute to the definition', () => {
    render(<Abbr term="SLA" />);
    const abbr = getAbbrEl();
    expect(abbr).toHaveAttribute('title', ABBR_TERMS.SLA);
  });

  it('applies extra className to the abbr element', () => {
    render(<Abbr term="XP" className="my-custom-class" />);
    const abbr = getAbbrEl();
    expect(abbr?.className).toContain('my-custom-class');
  });

  it('renders just the children (no abbr) for an unknown term', () => {
    // @ts-expect-error intentionally passing unknown term
    render(<Abbr term="UNKNOWN_TERM">fallback</Abbr>);
    expect(screen.getByText('fallback')).toBeInTheDocument();
    expect(getAbbrEl()).toBeNull();
  });

  it('renders the term key as text when no children and term is unknown', () => {
    // @ts-expect-error intentionally passing unknown term
    render(<Abbr term="NOSUCHKEY" />);
    expect(screen.getByText('NOSUCHKEY')).toBeInTheDocument();
  });

  it('ABBR_TERMS contains expected keys', () => {
    expect(ABBR_TERMS).toHaveProperty('GDPR');
    expect(ABBR_TERMS).toHaveProperty('NEXUS');
    expect(ABBR_TERMS).toHaveProperty('XP');
    expect(typeof ABBR_TERMS.GDPR).toBe('string');
  });
});
