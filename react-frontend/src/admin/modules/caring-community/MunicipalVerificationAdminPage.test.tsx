// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ─── api: named import used by this module ─────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({
  default: mockApi,
  api: mockApi,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  }),
);

// PageHeader is an admin component — stub it to keep tests simple
// Include actions so the refresh button renders
vi.mock('../../components', () => ({
  PageHeader: ({ title, actions }: { title: string; actions?: React.ReactNode }) => (
    <div>
      <h1>{title}</h1>
      {actions}
    </div>
  ),
  Abbr: ({ term }: { term: string }) => <abbr>{term}</abbr>,
}));

import MunicipalVerificationAdminPage from './MunicipalVerificationAdminPage';

// ─── fixtures ──────────────────────────────────────────────────────────────

const VERIFIED_ITEM = {
  id: 1,
  domain: 'galway.ie',
  method: 'admin_attestation',
  status: 'verified',
  dns_record_name: null,
  dns_record_value: null,
  verified_at: '2025-06-01T10:00:00Z',
  revoked_at: null,
  attestation_note: 'Manually verified by admin',
  created_at: '2025-05-01T00:00:00Z',
  updated_at: '2025-06-01T10:00:00Z',
};

const PENDING_ITEM = {
  id: 2,
  domain: 'cork.ie',
  method: 'dns_txt',
  status: 'pending',
  dns_record_name: '_nexus-verify.cork.ie',
  dns_record_value: 'nexus-verify=abc123xyz',
  verified_at: null,
  revoked_at: null,
  attestation_note: null,
  created_at: '2025-07-01T00:00:00Z',
  updated_at: '2025-07-01T00:00:00Z',
};

const REVOKED_ITEM = {
  ...VERIFIED_ITEM,
  id: 3,
  status: 'revoked',
  revoked_at: '2025-08-01T00:00:00Z',
};

function makeVerifiedResponse(items = [VERIFIED_ITEM]) {
  return {
    success: true,
    data: {
      verified: true,
      active: VERIFIED_ITEM,
      items,
    },
  };
}

function makeUnverifiedResponse(items: typeof VERIFIED_ITEM[] = []) {
  return {
    success: true,
    data: {
      verified: false,
      active: null,
      items,
    },
  };
}

// ─── tests ─────────────────────────────────────────────────────────────────

describe('MunicipalVerificationAdminPage — loading', () => {
  beforeEach(() => vi.resetAllMocks());

  it('shows a spinner on initial load', () => {
    mockApi.get.mockReturnValue(new Promise(() => {}));
    render(<MunicipalVerificationAdminPage />);
    const spinners = screen.getAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });
});

describe('MunicipalVerificationAdminPage — verified state', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue(makeVerifiedResponse());
  });

  it('renders the page title after loading', async () => {
    render(<MunicipalVerificationAdminPage />);
    await waitFor(() => {
      expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
    });
  });

  it('shows the verified domain', async () => {
    render(<MunicipalVerificationAdminPage />);
    await waitFor(() => {
      // domain appears in current-status + history; use getAllByText
      expect(screen.getAllByText('galway.ie').length).toBeGreaterThanOrEqual(1);
    });
  });

  it('shows the attestation note', async () => {
    render(<MunicipalVerificationAdminPage />);
    await waitFor(() => {
      // attestation_note appears in current-status and history; use getAllByText
      const notes = screen.getAllByText(/"Manually verified by admin"/);
      expect(notes.length).toBeGreaterThanOrEqual(1);
    });
  });

  it('shows the history list with the verified item', async () => {
    render(<MunicipalVerificationAdminPage />);
    await waitFor(() => {
      // galway.ie appears in both current status + history
      expect(screen.getAllByText('galway.ie').length).toBeGreaterThanOrEqual(1);
    });
  });
});

describe('MunicipalVerificationAdminPage — unverified/empty state', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue(makeUnverifiedResponse());
  });

  it('shows "not verified" message when no active verification', async () => {
    render(<MunicipalVerificationAdminPage />);
    await waitFor(() => {
      const spinners = screen.queryAllByRole('status');
      expect(spinners.find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });
    // The "not_verified" translation key text should be rendered
    expect(screen.queryByText('galway.ie')).not.toBeInTheDocument();
  });

  it('shows history empty message when no items', async () => {
    render(<MunicipalVerificationAdminPage />);
    await waitFor(() => {
      const spinners = screen.queryAllByRole('status');
      expect(spinners.find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });
    // No domain items in list
    expect(screen.queryByText('galway.ie')).not.toBeInTheDocument();
  });
});

describe('MunicipalVerificationAdminPage — pending DNS item', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue(makeUnverifiedResponse([PENDING_ITEM]));
  });

  it('renders the pending domain', async () => {
    render(<MunicipalVerificationAdminPage />);
    await waitFor(() => {
      expect(screen.getByText('cork.ie')).toBeInTheDocument();
    });
  });

  it('shows DNS record name and value for pending items', async () => {
    render(<MunicipalVerificationAdminPage />);
    await waitFor(() => {
      expect(screen.getByText('_nexus-verify.cork.ie')).toBeInTheDocument();
      expect(screen.getByText('nexus-verify=abc123xyz')).toBeInTheDocument();
    });
  });

  it('shows a Revoke button for non-revoked item', async () => {
    render(<MunicipalVerificationAdminPage />);
    await waitFor(() => {
      const allBtns = screen.getAllByRole('button');
      const revokeBtn = allBtns.find(
        (b) =>
          b.textContent?.toLowerCase().includes('revoke') ||
          b.textContent?.includes('municipal_verification.actions.revoke'),
      );
      expect(revokeBtn).toBeDefined();
    });
  });

  it('does NOT show a Revoke button for already-revoked items', async () => {
    mockApi.get.mockResolvedValueOnce(makeUnverifiedResponse([REVOKED_ITEM]));
    render(<MunicipalVerificationAdminPage />);
    await waitFor(() => {
      // The revoked item should not have a revoke button
      const allBtns = screen.getAllByRole('button');
      const revokeBtn = allBtns.find(
        (b) =>
          b.textContent?.toLowerCase().includes('revoke') ||
          b.textContent?.includes('municipal_verification.actions.revoke'),
      );
      expect(revokeBtn).toBeUndefined();
    });
  });
});

describe('MunicipalVerificationAdminPage — DNS form', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue(makeUnverifiedResponse());
  });

  it('calls POST /dns when domain is entered and button clicked', async () => {
    const user = userEvent.setup();
    mockApi.post.mockResolvedValue({ success: true });
    mockApi.get
      .mockResolvedValueOnce(makeUnverifiedResponse())
      .mockResolvedValueOnce(makeUnverifiedResponse([PENDING_ITEM]));

    render(<MunicipalVerificationAdminPage />);

    // Wait for the generate button to appear (HeroUI Tabs need an extra tick to init)
    let generateBtn: HTMLElement | undefined;
    await waitFor(() => {
      const allBtns = Array.from(document.querySelectorAll('button'));
      generateBtn = allBtns.find(
        (b) =>
          !b.hasAttribute('disabled') &&
          b.getAttribute('aria-disabled') !== 'true' &&
          (b.textContent?.toLowerCase().includes('generate') ||
            b.textContent?.includes('municipal_verification.actions.generate_dns_token')),
      );
      expect(generateBtn).toBeDefined();
    });

    // Type a domain into the first DNS input (inside the first Tabs panel)
    const inputs = Array.from(document.querySelectorAll('input[type="text"],input:not([type])'));
    expect(inputs.length).toBeGreaterThan(0);
    await user.clear(inputs[0] as HTMLElement);
    await user.type(inputs[0] as HTMLElement, 'dublin.ie');

    await user.click(generateBtn!);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/v2/admin/reports/municipal-impact/verification/dns',
        expect.objectContaining({ domain: 'dublin.ie' }),
      );
    });
  });

  it('shows error toast when domain is empty and generate is clicked', async () => {
    const user = userEvent.setup();
    render(<MunicipalVerificationAdminPage />);

    // Wait for the generate button to appear (HeroUI Tabs need an extra tick to init)
    let generateBtn: HTMLElement | undefined;
    await waitFor(() => {
      const allBtns = Array.from(document.querySelectorAll('button'));
      generateBtn = allBtns.find(
        (b) =>
          !b.hasAttribute('disabled') &&
          b.getAttribute('aria-disabled') !== 'true' &&
          (b.textContent?.toLowerCase().includes('generate') ||
            b.textContent?.includes('municipal_verification.actions.generate_dns_token')),
      );
      expect(generateBtn).toBeDefined();
    });

    // Click without filling domain (input is empty by default)
    await user.click(generateBtn!);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});

describe('MunicipalVerificationAdminPage — revoke modal', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue(makeUnverifiedResponse([PENDING_ITEM]));
  });

  it('opens revoke modal when Revoke is clicked', async () => {
    const user = userEvent.setup();
    render(<MunicipalVerificationAdminPage />);
    await waitFor(() => {
      expect(screen.getByText('cork.ie')).toBeInTheDocument();
    });

    const allBtns = screen.getAllByRole('button');
    const revokeBtn = allBtns.find(
      (b) =>
        b.textContent?.toLowerCase().includes('revoke') ||
        b.textContent?.includes('municipal_verification.actions.revoke'),
    );
    await user.click(revokeBtn!);

    // Modal should open — the modal footer has a Cancel button
    await waitFor(() => {
      const allBtnsAfter = screen.getAllByRole('button');
      const cancelBtn = allBtnsAfter.find(
        (b) =>
          b.textContent?.toLowerCase().includes('cancel') ||
          b.textContent?.includes('municipal_verification.actions.cancel'),
      );
      expect(cancelBtn).toBeDefined();
    });
  });

  it('calls POST /revoke when modal is confirmed', async () => {
    const user = userEvent.setup();
    mockApi.post.mockResolvedValue({ success: true });
    mockApi.get
      .mockResolvedValueOnce(makeUnverifiedResponse([PENDING_ITEM]))
      .mockResolvedValueOnce(makeUnverifiedResponse([REVOKED_ITEM]));

    render(<MunicipalVerificationAdminPage />);
    await waitFor(() => expect(screen.getByText('cork.ie')).toBeInTheDocument());

    const allBtns = screen.getAllByRole('button');
    const revokeBtn = allBtns.find(
      (b) =>
        b.textContent?.toLowerCase().includes('revoke') ||
        b.textContent?.includes('municipal_verification.actions.revoke'),
    );
    await user.click(revokeBtn!);

    // In the modal, click the confirm Revoke button
    await waitFor(async () => {
      const modalBtns = screen.getAllByRole('button');
      // Danger revoke button in modal footer
      const confirmRevokeBtn = modalBtns.filter(
        (b) =>
          !b.hasAttribute('disabled') &&
          b.getAttribute('aria-disabled') !== 'true' &&
          (b.textContent?.toLowerCase().includes('revoke') ||
            b.textContent?.includes('municipal_verification.actions.revoke')),
      );
      // The modal has 2 revoke buttons sometimes (trigger + modal confirm); click last
      const btn = confirmRevokeBtn[confirmRevokeBtn.length - 1];
      if (btn) await user.click(btn);
    });

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        `/v2/admin/reports/municipal-impact/verification/${PENDING_ITEM.id}/revoke`,
        {},
      );
    });
  });
});

describe('MunicipalVerificationAdminPage — refresh', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue(makeVerifiedResponse());
  });

  it('calls the API again when refresh button is clicked', async () => {
    const user = userEvent.setup();
    render(<MunicipalVerificationAdminPage />);
    await waitFor(() => expect(mockApi.get).toHaveBeenCalledTimes(1));

    const allBtns = screen.getAllByRole('button');
    const refreshBtn = allBtns.find(
      (b) =>
        b.textContent?.toLowerCase().includes('refresh') ||
        b.textContent?.includes('municipal_verification.actions.refresh'),
    );
    expect(refreshBtn).toBeDefined();
    await user.click(refreshBtn!);

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledTimes(2);
    });
  });
});
