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

vi.mock('framer-motion', () => framerMotionMock);

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (_key: string, fallback: string, _opts?: object) => fallback ?? _key,
  }),
}));

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [] }),
    upload: vi.fn().mockResolvedValue({ success: true }),
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

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div data-testid="glass-card" className={className}>{children}</div>
  ),
}));

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

import { CredentialVerificationTab } from './CredentialVerificationTab';
import { api } from '@/lib/api';

const mockCredential = {
  id: 1,
  type: 'police_check',
  type_label: 'Police Check',
  document_name: 'police_check.pdf',
  upload_date: '2026-01-10T10:00:00Z',
  expiry_date: null,
  status: 'verified' as const,
  rejection_reason: null,
};

const mockPendingCredential = {
  id: 2,
  type: 'first_aid',
  type_label: 'First Aid',
  document_name: 'first_aid_cert.pdf',
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

describe('CredentialVerificationTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the heading and upload button', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<CredentialVerificationTab />);
    expect(screen.getByText('Credential Verification')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Upload New Credential/i })).toBeInTheDocument();
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
    expect(screen.getByRole('button', { name: /Try Again/i })).toBeInTheDocument();
  });

  it('displays credential list items when credentials exist', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { credentials: [mockCredential, mockPendingCredential] },
    });
    render(<CredentialVerificationTab />);
    await waitFor(() => {
      expect(screen.getByText('Police Check')).toBeInTheDocument();
    });
    expect(screen.getByText('First Aid')).toBeInTheDocument();
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
    expect(screen.getByText(/Document is not legible/)).toBeInTheDocument();
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
    vi.mocked(api.get).mockResolvedValueOnce({ success: false, data: null });
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<CredentialVerificationTab />);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Try Again/i })).toBeInTheDocument();
    });
    fireEvent.click(screen.getByRole('button', { name: /Try Again/i }));
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledTimes(2);
    });
  });

  it('does not show Re-upload button for verified credentials', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { credentials: [mockCredential] },
    });
    render(<CredentialVerificationTab />);
    await waitFor(() => {
      expect(screen.getByText('Police Check')).toBeInTheDocument();
    });
    expect(screen.queryByRole('button', { name: /Re-upload/i })).not.toBeInTheDocument();
  });
});
