// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for CredentialVerificationTab
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { framerMotionMock } from '@/test/mocks';

vi.mock('@/lib/motion', () => framerMotionMock);

// Stable t function reference to avoid useCallback/useEffect re-trigger loops
const credentialTranslations: Record<string, string> = {
  'credentials.heading': 'Credential Verification',
  'credentials.load_failed': 'Failed to load credentials.',
  'credentials.no_credentials_title': 'No credentials uploaded',
  'credentials.re_upload': 'Re-upload',
  'credentials.try_again': 'Try Again',
  'credentials.upload_new': 'Upload New Credential',
  'credentials.vetting_documents_notice_title': 'Do not upload police-check documents',
  'credentials.vetting_documents_notice_body': 'DBS and police-check documents are not accepted here.',
  'credentials.legacy_vetting_evidence_title': 'Legacy police-check document must be removed',
  'credentials.legacy_vetting_evidence_body': 'This historical upload is redacted and must be deleted.',
  'credentials.legacy_vetting_evidence_delete': 'Delete legacy document',
  'credentials.legacy_vetting_evidence_delete_success': 'Legacy police-check document deleted.',
  'credentials.legacy_vetting_evidence_delete_failed': 'The legacy document could not be deleted.',
  'credentials.status_verified': 'Verified',
  'credentials.status_pending': 'Awaiting review',
  'credentials.status_manual_review': 'Manual review required',
  'credentials.manual_review_title': 'Unsupported historical credential',
  'credentials.manual_review_body': 'This historical credential type needs manual review. Its document details are hidden.',
  'credentials.manual_review_delete': 'Delete credential',
  'credentials.manual_review_delete_success': 'Credential deleted.',
  'credentials.manual_review_delete_failed': 'The credential could not be deleted.',
};

const stableT = (key: string, fallbackOrOptions?: string | { fallbackValue?: string }, _opts?: object) => {
  if (typeof fallbackOrOptions === 'string') {
    return fallbackOrOptions;
  }

  return credentialTranslations[key] ?? fallbackOrOptions?.fallbackValue ?? key;
};
vi.mock('react-i18next', () => ({
  initReactI18next: {
    type: '3rdParty',
    init: vi.fn(),
  },
  useTranslation: () => ({
    t: stableT,
  }),
}));

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [] }),
    upload: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
  },
}));

vi.mock('@/contexts/ToastContext', () => ({
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  })),
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/components/ui', async () => (await import('@/test/uiMock')).uiMock);

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description }: { title: string; description?: string }) => (
    <div data-testid="empty-state">
      <div>{title}</div>
      {description && <div>{description}</div>}
    </div>
  ),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

import { CREDENTIAL_TYPE_KEYS, CredentialVerificationTab } from './CredentialVerificationTab';
import { api } from '@/lib/api';

const mockCredential = {
  id: 1,
  type: 'first_aid',
  type_label: 'First Aid',
  document_name: 'first_aid.pdf',
  upload_date: '2026-01-10T10:00:00Z',
  expiry_date: null,
  status: 'verified' as const,
  rejection_reason: null,
};

const mockPendingCredential = {
  id: 2,
  type: 'manual_handling',
  type_label: 'Manual Handling',
  document_name: 'manual_handling.pdf',
  upload_date: '2026-02-01T10:00:00Z',
  expiry_date: null,
  status: 'pending' as const,
  rejection_reason: null,
};

const mockRejectedCredential = {
  id: 3,
  type: 'safeguarding',
  type_label: 'Safeguarding',
  document_name: 'safeguarding.pdf',
  upload_date: '2025-12-01T10:00:00Z',
  expiry_date: null,
  status: 'rejected' as const,
  rejection_reason: 'Document is not legible.',
};

const mockLegacyVettingEvidence = {
  id: 4,
  legacy_vetting_evidence: true as const,
  type: 'police_check',
  type_label: 'Police Check',
  document_name: 'sensitive-police-check.pdf',
  upload_date: '2025-12-01T10:00:00Z',
  expiry_date: '2027-12-01',
  status: 'verified' as const,
  rejection_reason: 'Sensitive legacy note',
};

const mockManualReviewCredential = {
  id: 5,
  type: 'custom_community_badge',
  type_label: 'Custom Community Badge',
  document_name: 'secret-custom-badge.pdf',
  upload_date: '2025-12-01T10:00:00Z',
  expiry_date: '2027-12-01',
  status: 'verified' as const,
  rejection_reason: 'Historical internal note',
  legacy_vetting_evidence: false as const,
  manual_review_required: true as const,
};

describe('CredentialVerificationTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the heading and upload button', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<CredentialVerificationTab />);
    expect(screen.getByText('Credential Verification')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Upload New Credential/i })).toBeInTheDocument();
    expect(screen.getByText('Do not upload police-check documents')).toBeInTheDocument();
  });

  it('does not offer police, background-check, or DBS credential types', () => {
    expect(CREDENTIAL_TYPE_KEYS).not.toContain('police_check');
    expect(CREDENTIAL_TYPE_KEYS).not.toContain('background_check');
    expect(CREDENTIAL_TYPE_KEYS).not.toContain('dbs');
    expect(CREDENTIAL_TYPE_KEYS).not.toContain('dbs_enhanced');
    expect(CREDENTIAL_TYPE_KEYS).toContain('safeguarding');
  });

  it('shows empty state when no credentials exist', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<CredentialVerificationTab />);
    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
      expect(screen.getByText('No credentials uploaded')).toBeInTheDocument();
    });
  });

  it('shows error state and retry button when API fails', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: false, data: null });
    render(<CredentialVerificationTab />);
    await waitFor(() => {
      expect(screen.getByText('Failed to load credentials.')).toBeInTheDocument();
    });
    // HeroUI Button accessible name — find by text content within waitFor to ensure render completes
    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      const tryAgainBtn = buttons.find(btn => btn.textContent?.includes('Try Again'));
      expect(tryAgainBtn).toBeTruthy();
    });
  });

  it('displays credential list items when credentials exist', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { credentials: [mockCredential, mockPendingCredential] },
    });
    render(<CredentialVerificationTab />);
    await waitFor(() => {
      expect(screen.getByText('First Aid')).toBeInTheDocument();
      expect(screen.getByText('Manual Handling')).toBeInTheDocument();
    });
  });

  it('shows rejection reason for rejected credentials', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { credentials: [mockRejectedCredential] },
    });
    render(<CredentialVerificationTab />);
    await waitFor(() => {
      expect(screen.getByText('Safeguarding')).toBeInTheDocument();
    });
    // Rejection reason text is split across <strong> and text node inside a <p>;
    // search the full document text content instead.
    await waitFor(() => {
      expect(document.body.textContent).toContain('Document is not legible');
    });
  });

  it('shows Re-upload button for rejected credentials', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { credentials: [mockRejectedCredential] },
    });
    render(<CredentialVerificationTab />);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Re-upload/i })).toBeInTheDocument();
    });
  });

  it('retries loading when Try Again is clicked', async () => {
    let callCount = 0;
    vi.mocked(api.get).mockImplementation(() => {
      callCount++;
      if (callCount === 1) {
        return Promise.resolve({ success: false, data: null });
      }
      return Promise.resolve({ success: true, data: [] });
    });
    render(<CredentialVerificationTab />);
    // Wait for error state to fully render including the button
    let tryAgainBtn: HTMLElement | undefined;
    await waitFor(() => {
      expect(screen.getByText('Failed to load credentials.')).toBeInTheDocument();
      const buttons = screen.getAllByRole('button');
      tryAgainBtn = buttons.find(btn => btn.textContent?.includes('Try Again'));
      expect(tryAgainBtn).toBeTruthy();
    });
    fireEvent.click(tryAgainBtn!);
    await waitFor(() => {
      expect(callCount).toBe(2);
    });
  });

  it('does not show Re-upload button for verified credentials', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { credentials: [mockCredential] },
    });
    render(<CredentialVerificationTab />);
    await waitFor(() => {
      expect(screen.getByText('First Aid')).toBeInTheDocument();
    });
    expect(screen.queryByRole('button', { name: /Re-upload/i })).not.toBeInTheDocument();
  });

  it('redacts legacy vetting evidence and deletes it without exposing metadata', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { credentials: [mockLegacyVettingEvidence] },
    });
    render(<CredentialVerificationTab />);

    await waitFor(() => {
      expect(screen.getByText('Legacy police-check document must be removed')).toBeInTheDocument();
    });
    expect(screen.queryByText('Police Check')).not.toBeInTheDocument();
    expect(screen.queryByText('sensitive-police-check.pdf')).not.toBeInTheDocument();
    expect(screen.queryByText('Sensitive legacy note')).not.toBeInTheDocument();
    expect(screen.queryByText(/2027/)).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Re-upload/i })).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Delete legacy document' }));
    await waitFor(() => {
      expect(api.delete).toHaveBeenCalledWith('/v2/volunteering/credentials/4');
    });
    expect(screen.queryByText('Legacy police-check document must be removed')).not.toBeInTheDocument();
  });

  it('renders an unknown credential as manual review without counting or exposing its metadata', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { credentials: [mockManualReviewCredential] },
    });
    render(<CredentialVerificationTab />);

    await waitFor(() => {
      expect(screen.getByText('Custom Community Badge')).toBeInTheDocument();
      expect(screen.getByText('Manual review required')).toBeInTheDocument();
    });

    const verifiedSummaryLabel = screen.getByText('Verified');
    expect(verifiedSummaryLabel.previousElementSibling).toHaveTextContent('0');
    expect(screen.queryByText('secret-custom-badge.pdf')).not.toBeInTheDocument();
    expect(screen.queryByText('Historical internal note')).not.toBeInTheDocument();
    expect(screen.queryByText(/2027/)).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Re-upload/i })).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Delete credential' }));
    await waitFor(() => {
      expect(api.delete).toHaveBeenCalledWith('/v2/volunteering/credentials/5');
    });
    expect(screen.queryByText('Custom Community Badge')).not.toBeInTheDocument();
  });
});
