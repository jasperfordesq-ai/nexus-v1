// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { CustomLegalDocument } from './CustomLegalDocument';
import type { LegalDocument } from '@/hooks/useLegalDocument';

// Mock framer-motion to strip animation props
vi.mock('framer-motion', () => {
  const handler = {
    get: (_: any, tag: string) => {
      return ({
        children,
        initial,
        animate,
        exit,
        transition,
        variants,
        whileHover,
        whileTap,
        whileInView,
        ...rest
      }: any) => {
        const Tag = typeof tag === 'string' ? tag : 'div';
        return <Tag {...rest}>{children}</Tag>;
      };
    },
  };
  return {
    motion: new Proxy({}, handler),
    AnimatePresence: ({ children }: any) => children,
  };
});

// Mock TenantContext
vi.mock('@/contexts', () => ({
  useTenant: vi.fn(() => ({
    tenantPath: vi.fn((p: string) => `/test-tenant${p}`),
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    isLoading: false,
  })),
}));

function makeDoc(overrides: Partial<LegalDocument> = {}): LegalDocument {
  return {
    id: 1,
    document_id: 1,
    type: 'terms',
    title: 'Terms of Service',
    content: '<h2>Section One</h2><p>Content for section one.</p>',
    version_number: '1.0',
    effective_date: '2026-01-15',
    summary_of_changes: null,
    has_previous_versions: false,
    ...overrides,
  };
}

describe('CustomLegalDocument', () => {
  it('renders the document title', () => {
    render(<CustomLegalDocument document={makeDoc()} />);
    expect(screen.getByText('Terms of Service')).toBeInTheDocument();
  });

  it('renders the effective date', () => {
    render(<CustomLegalDocument document={makeDoc()} />);
    // The component formats the date using en-IE locale
    expect(screen.getByText(/Effective:/)).toBeInTheDocument();
  });

  it('renders the version number chip', () => {
    render(<CustomLegalDocument document={makeDoc({ version_number: '2.1' })} />);
    expect(screen.getByText('v2.1')).toBeInTheDocument();
  });

  it('renders summary of changes when present', () => {
    render(
      <CustomLegalDocument
        document={makeDoc({ summary_of_changes: 'Updated privacy clauses' })}
      />
    );
    expect(screen.getByText('Updated privacy clauses')).toBeInTheDocument();
  });

  it('parses sections from HTML h2 tags', () => {
    const content =
      '<h2>First Section</h2><p>First body</p>' +
      '<h2>Second Section</h2><p>Second body</p>';
    render(<CustomLegalDocument document={makeDoc({ content })} />);
    expect(screen.getByText('First Section')).toBeInTheDocument();
    expect(screen.getByText('Second Section')).toBeInTheDocument();
  });

  it('shows table of contents when 4+ sections', () => {
    const content =
      '<h2>Section A</h2><p>A</p>' +
      '<h2>Section B</h2><p>B</p>' +
      '<h2>Section C</h2><p>C</p>' +
      '<h2>Section D</h2><p>D</p>';
    render(<CustomLegalDocument document={makeDoc({ content })} />);
    expect(screen.getByText('Contents')).toBeInTheDocument();
  });

  it('does NOT show table of contents when fewer than 4 sections', () => {
    const content =
      '<h2>Section A</h2><p>A</p>' +
      '<h2>Section B</h2><p>B</p>';
    render(<CustomLegalDocument document={makeDoc({ content })} />);
    expect(screen.queryByText('Contents')).not.toBeInTheDocument();
  });

  it('handles numbered sections (parseNumberedTitle) — uses document numbers in TOC', () => {
    const content =
      '<h2>1. Definitions</h2><p>Def text</p>' +
      '<h2>2. Scope</h2><p>Scope text</p>' +
      '<h2>3. Eligibility</h2><p>Elig text</p>' +
      '<h2>4. Accounts</h2><p>Acc text</p>';
    render(<CustomLegalDocument document={makeDoc({ content })} />);

    // When document has its own numbering, displayTitle is used (without prefix)
    expect(screen.getAllByText('Definitions').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('Scope').length).toBeGreaterThanOrEqual(1);

    // Numeric prefixes should appear as chip labels
    // "1" chip appears for section 1 in both TOC and content heading
    const ones = screen.getAllByText('1');
    expect(ones.length).toBeGreaterThanOrEqual(1);
  });

  it('renders section HTML content', () => {
    const content = '<h2>Test</h2><p>Paragraph with <strong>bold text</strong></p>';
    const { container } = render(
      <CustomLegalDocument document={makeDoc({ content })} />
    );
    const legalContent = container.querySelector('.legal-content');
    expect(legalContent).not.toBeNull();
    expect(legalContent!.innerHTML).toContain('<strong>bold text</strong>');
  });

  it('renders "Have Questions?" section with contact link', () => {
    render(<CustomLegalDocument document={makeDoc()} />);
    expect(screen.getByText('Have Questions?')).toBeInTheDocument();
    expect(screen.getByText('Contact Us')).toBeInTheDocument();
  });

  it('shows version history link when has_previous_versions is true', () => {
    render(
      <CustomLegalDocument
        document={makeDoc({ has_previous_versions: true })}
      />
    );
    expect(
      screen.getByText('View previous versions of this document')
    ).toBeInTheDocument();
  });

  it('does NOT show version history link when has_previous_versions is false', () => {
    render(
      <CustomLegalDocument
        document={makeDoc({ has_previous_versions: false })}
      />
    );
    expect(
      screen.queryByText('View previous versions of this document')
    ).not.toBeInTheDocument();
  });

  it('handles content with intro text before first h2', () => {
    const content =
      '<p>This is an introductory paragraph with enough text to pass the length check.</p>' +
      '<h2>First Section</h2><p>Section content</p>';
    render(<CustomLegalDocument document={makeDoc({ content })} />);
    expect(screen.getByText('Introduction')).toBeInTheDocument();
    expect(screen.getByText('First Section')).toBeInTheDocument();
  });
});
