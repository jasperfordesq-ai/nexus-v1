// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';
import type { PendingDocument } from '@/hooks/useLegalGate';

// ─── No API calls — LegalAcceptanceGate receives props; no internal fetching ──

// ─── Contexts ─────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Stub HeroUI Modal family ─────────────────────────────────────────────────
// ModalContent children is a render prop: (onClose) => ReactNode
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<Record<string, unknown>>();
  return {
    ...orig,
    Modal: ({ children, isOpen }: { children: React.ReactNode; isOpen?: boolean; isDismissable?: boolean; hideCloseButton?: boolean; size?: string; classNames?: object; 'aria-labelledby'?: string }) => (
      isOpen ? <div role="dialog" aria-label="Dialog" data-testid="legal-gate-modal">{children}</div> : null
    ),
    ModalContent: ({ children }: { children: ((onClose: () => void) => React.ReactNode) | React.ReactNode }) => (
      <div data-testid="modal-content">
        {typeof children === 'function' ? children(() => {}) : children}
      </div>
    ),
    ModalHeader: ({ children, id, className }: { children: React.ReactNode; id?: string; className?: string }) => (
      <div data-testid="modal-header" id={id} className={className}>{children}</div>
    ),
    ModalBody: ({ children, className }: { children: React.ReactNode; className?: string }) => (
      <div data-testid="modal-body" className={className}>{children}</div>
    ),
    ModalFooter: ({ children }: { children: React.ReactNode }) => (
      <div data-testid="modal-footer">{children}</div>
    ),
    Button: ({
      children, onPress, isLoading, isDisabled, color, 'aria-label': ariaLabel,
    }: {
      children?: React.ReactNode; onPress?: () => void; isLoading?: boolean; isDisabled?: boolean;
      color?: string; 'aria-label'?: string;
    }) => (
      <button
        onClick={() => onPress?.()}
        disabled={isDisabled || isLoading}
        aria-label={ariaLabel}
        aria-busy={isLoading ? 'true' : undefined}
        data-color={color}
      >
        {children}
      </button>
    ),
    Chip: ({ children, color, variant, size, className }: {
      children: React.ReactNode; color?: string; variant?: string; size?: string; className?: string;
    }) => (
      <span data-testid="doc-chip" data-color={color} className={className}>{children}</span>
    ),
  };
});

// ─── Fixtures ────────────────────────────────────────────────────────────────
const makeDoc = (overrides: Partial<PendingDocument> = {}): PendingDocument => ({
  document_id: 1,
  document_type: 'terms',
  title: 'Terms of Service',
  current_version_id: 3,
  current_version: '1.2',
  acceptance_status: 'not_accepted',
  accepted_at: null,
  ...overrides,
});

const defaultProps = {
  pendingDocs: [makeDoc()],
  onAcceptAll: vi.fn().mockResolvedValue(undefined),
  isAccepting: false,
};

// ─────────────────────────────────────────────────────────────────────────────
describe('LegalAcceptanceGate', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    defaultProps.onAcceptAll = vi.fn().mockResolvedValue(undefined);
  });

  it('renders the modal dialog when pendingDocs are provided', async () => {
    const { LegalAcceptanceGate } = await import('./LegalAcceptanceGate');
    render(<LegalAcceptanceGate {...defaultProps} />);

    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  it('renders the modal header', async () => {
    const { LegalAcceptanceGate } = await import('./LegalAcceptanceGate');
    render(<LegalAcceptanceGate {...defaultProps} />);

    expect(screen.getByTestId('modal-header')).toBeInTheDocument();
  });

  it('renders a document row for each pending document', async () => {
    const docs = [
      makeDoc({ document_id: 1, document_type: 'terms', title: 'Terms of Service' }),
      makeDoc({ document_id: 2, document_type: 'privacy', title: 'Privacy Policy' }),
    ];
    const { LegalAcceptanceGate } = await import('./LegalAcceptanceGate');
    render(<LegalAcceptanceGate {...defaultProps} pendingDocs={docs} />);

    // Each document type has a translated label; for unknown fallback it shows title
    // Both docs are present in the DOM
    expect(screen.getByTestId('modal-body')).toBeInTheDocument();
    // There are 2 "Read" links
    const links = screen.getAllByRole('link');
    expect(links.length).toBe(2);
  });

  it('renders a Read link for each document', async () => {
    const { LegalAcceptanceGate } = await import('./LegalAcceptanceGate');
    render(<LegalAcceptanceGate {...defaultProps} pendingDocs={[makeDoc({ document_type: 'privacy' })]} />);

    const links = screen.getAllByRole('link');
    expect(links.length).toBeGreaterThan(0);
  });

  it('document Read link points to the correct tenant path', async () => {
    const { LegalAcceptanceGate } = await import('./LegalAcceptanceGate');
    render(<LegalAcceptanceGate {...defaultProps} pendingDocs={[makeDoc({ document_type: 'terms' })]} />);

    const links = screen.getAllByRole('link');
    const termsLink = links.find((l) => l.getAttribute('href')?.includes('/terms'));
    expect(termsLink).toBeDefined();
  });

  it('renders an "Updated" chip for outdated documents', async () => {
    const outdatedDoc = makeDoc({ acceptance_status: 'outdated' });
    const { LegalAcceptanceGate } = await import('./LegalAcceptanceGate');
    render(<LegalAcceptanceGate {...defaultProps} pendingDocs={[outdatedDoc]} />);

    const chips = screen.getAllByTestId('doc-chip');
    expect(chips.length).toBeGreaterThan(0);
  });

  it('does not render an "Updated" chip for not_accepted documents', async () => {
    const freshDoc = makeDoc({ acceptance_status: 'not_accepted' });
    const { LegalAcceptanceGate } = await import('./LegalAcceptanceGate');
    render(<LegalAcceptanceGate {...defaultProps} pendingDocs={[freshDoc]} />);

    const chips = screen.queryAllByTestId('doc-chip');
    expect(chips.length).toBe(0);
  });

  it('renders the accept button in the footer', async () => {
    const { LegalAcceptanceGate } = await import('./LegalAcceptanceGate');
    render(<LegalAcceptanceGate {...defaultProps} />);

    const footer = screen.getByTestId('modal-footer');
    const btn = footer.querySelector('button');
    expect(btn).toBeTruthy();
  });

  it('calls onAcceptAll when the accept button is clicked', async () => {
    const onAcceptAll = vi.fn().mockResolvedValue(undefined);
    const { LegalAcceptanceGate } = await import('./LegalAcceptanceGate');
    render(<LegalAcceptanceGate {...defaultProps} onAcceptAll={onAcceptAll} />);

    const footer = screen.getByTestId('modal-footer');
    const btn = footer.querySelector('button') as HTMLButtonElement;
    fireEvent.click(btn);

    await waitFor(() => {
      expect(onAcceptAll).toHaveBeenCalledTimes(1);
    });
  });

  it('accept button is disabled when isAccepting=true', async () => {
    const { LegalAcceptanceGate } = await import('./LegalAcceptanceGate');
    render(<LegalAcceptanceGate {...defaultProps} isAccepting={true} />);

    const footer = screen.getByTestId('modal-footer');
    const btn = footer.querySelector('button') as HTMLButtonElement;
    expect(btn.disabled).toBe(true);
  });

  it('accept button shows loading text when isAccepting=true', async () => {
    const { LegalAcceptanceGate } = await import('./LegalAcceptanceGate');
    render(<LegalAcceptanceGate {...defaultProps} isAccepting={true} />);

    // When isAccepting, button renders t('gate.accepting') text
    const footer = screen.getByTestId('modal-footer');
    expect(footer.querySelector('button')).toBeTruthy();
    // The button content changes — verify the element is aria-busy
    const btn = footer.querySelector('button') as HTMLButtonElement;
    expect(btn.getAttribute('aria-busy')).toBe('true');
  });

  it('shows subtitle for a single pending document', async () => {
    const { LegalAcceptanceGate } = await import('./LegalAcceptanceGate');
    render(<LegalAcceptanceGate {...defaultProps} pendingDocs={[makeDoc()]} />);

    const header = screen.getByTestId('modal-header');
    // Subtitle is rendered as a paragraph; just verify header is not empty
    expect(header.textContent?.length).toBeGreaterThan(0);
  });

  it('shows subtitle for multiple pending documents', async () => {
    const docs = [makeDoc({ document_id: 1 }), makeDoc({ document_id: 2, document_type: 'privacy' })];
    const { LegalAcceptanceGate } = await import('./LegalAcceptanceGate');
    render(<LegalAcceptanceGate {...defaultProps} pendingDocs={docs} />);

    const header = screen.getByTestId('modal-header');
    expect(header.textContent?.length).toBeGreaterThan(0);
  });

  it('renders consent text in the footer', async () => {
    const { LegalAcceptanceGate } = await import('./LegalAcceptanceGate');
    render(<LegalAcceptanceGate {...defaultProps} />);

    const footer = screen.getByTestId('modal-footer');
    // Consent text paragraph is present beside the button
    expect(footer.textContent?.length).toBeGreaterThan(0);
  });

  it('document read links open in a new tab', async () => {
    const { LegalAcceptanceGate } = await import('./LegalAcceptanceGate');
    render(<LegalAcceptanceGate {...defaultProps} />);

    const links = screen.getAllByRole('link');
    links.forEach((link) => {
      expect(link.getAttribute('target')).toBe('_blank');
    });
  });

  it('community_guidelines doc type renders a link to /community-guidelines', async () => {
    const { LegalAcceptanceGate } = await import('./LegalAcceptanceGate');
    render(<LegalAcceptanceGate {...defaultProps} pendingDocs={[makeDoc({ document_type: 'community_guidelines' })]} />);

    const links = screen.getAllByRole('link');
    const cgLink = links.find((l) => l.getAttribute('href')?.includes('community-guidelines'));
    expect(cgLink).toBeDefined();
  });
});
