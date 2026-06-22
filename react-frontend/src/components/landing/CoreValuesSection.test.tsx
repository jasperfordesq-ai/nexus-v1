// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import { CoreValuesSection } from './CoreValuesSection';

vi.mock('@/contexts', () => createMockContexts());

// @/lib/motion shim is CSS-only so we don't mock it; it renders plain divs.

describe('CoreValuesSection', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the section heading from i18n when no content prop is supplied', () => {
    render(<CoreValuesSection />);
    // i18n en translation: "Why Time Banking?"
    expect(screen.getByRole('heading', { level: 2 })).toHaveTextContent(/why time banking\?/i);
  });

  it('renders three default value cards when no content prop is supplied', () => {
    render(<CoreValuesSection />);
    const headings = screen.getAllByRole('heading', { level: 3 });
    expect(headings).toHaveLength(3);
  });

  it('labels default value cards 1, 2, 3', () => {
    render(<CoreValuesSection />);
    // Each card shows its ordinal (1/2/3) in an aria-hidden span
    expect(screen.getByText('1')).toBeInTheDocument();
    expect(screen.getByText('2')).toBeInTheDocument();
    expect(screen.getByText('3')).toBeInTheDocument();
  });

  it('renders a custom title from content prop', () => {
    render(
      <CoreValuesSection
        content={{
          title: 'Our Core Principles',
          subtitle: 'We believe in these.',
          values: [],
        }}
      />
    );
    expect(screen.getByRole('heading', { level: 2 })).toHaveTextContent('Our Core Principles');
  });

  it('renders custom values when content.values is non-empty', () => {
    render(
      <CoreValuesSection
        content={{
          title: 'Values',
          subtitle: '',
          values: [
            { title: 'Trust', description: 'We trust each other.' },
            { title: 'Care', description: 'We care deeply.' },
          ],
        }}
      />
    );
    expect(screen.getByText('Trust')).toBeInTheDocument();
    expect(screen.getByText('Care')).toBeInTheDocument();
    expect(screen.getByText('We trust each other.')).toBeInTheDocument();
    expect(screen.getByText('We care deeply.')).toBeInTheDocument();
    // Only 2 ordinal labels should appear
    expect(screen.getByText('1')).toBeInTheDocument();
    expect(screen.getByText('2')).toBeInTheDocument();
    expect(screen.queryByText('3')).not.toBeInTheDocument();
  });

  it('falls back to default values when content.values is an empty array', () => {
    render(
      <CoreValuesSection
        content={{ title: undefined, subtitle: undefined, values: [] }}
      />
    );
    // Empty values array → falls back to the 3 i18n defaults
    const headings = screen.getAllByRole('heading', { level: 3 });
    expect(headings).toHaveLength(3);
  });

  it('uses the section aria-labelledby attribute pointing at the h2', () => {
    render(<CoreValuesSection />);
    const section = document.querySelector('section[aria-labelledby="core-values-heading"]');
    expect(section).toBeInTheDocument();
    const h2 = document.getElementById('core-values-heading');
    expect(h2).toBeInTheDocument();
  });
});
