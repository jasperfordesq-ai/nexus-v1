// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for LegalDocForm admin module
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── stable mock factories ─────────────────────────────────────────────────────
const mockToast = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }));
const mockNavigate = vi.hoisted(() => vi.fn());

const EXISTING_DOC = vi.hoisted(() => ({
  id: 42,
  title: 'Privacy Policy',
  content: 'We respect your privacy.',
  type: 'privacy',
  version: '2.0',
  status: 'published',
}));

// ── module mocks ──────────────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts({
  useToast: () => mockToast,
  useTenant: () => ({
    tenant: { id: 2, name: 'Test', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    refreshTenant: vi.fn(),
  }),
}));

// useParams drives isEdit — mock for both "create" (no id) and "edit" (id=42) scenarios
let mockParamsId: string | undefined = undefined;

vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...actual,
    useNavigate: () => mockNavigate,
    useParams: () => ({ id: mockParamsId }),
  };
});

vi.mock('../../api/adminApi', () => ({
  adminLegalDocs: {
    get: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    delete: vi.fn(),
    list: vi.fn(),
  },
  adminDeliverability: { list: vi.fn(), delete: vi.fn() },
  adminPages: { list: vi.fn(), delete: vi.fn() },
  adminEnterprise: { getGdprRequests: vi.fn(), updateGdprRequest: vi.fn() },
  adminGamification: { getBadgeConfig: vi.fn(), updateBadgeConfig: vi.fn(), resetBadgeConfig: vi.fn() },
}));

vi.mock('../../AdminMetaContext', () => ({
  useAdminPageMeta: vi.fn(),
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import { LegalDocForm } from './LegalDocForm';
import { adminLegalDocs } from '../../api/adminApi';

// ─────────────────────────────────────────────────────────────────────────────

describe('LegalDocForm — create mode (no id)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockParamsId = undefined; // create mode
  });

  it('renders the form immediately (no loading spinner)', () => {
    render(<LegalDocForm />);

    // No spinner because loading=false in create mode
    const busy = screen.queryAllByRole('status').find(
      (el) => el.getAttribute('aria-busy') === 'true',
    );
    expect(busy).toBeUndefined();
  });

  it('renders title input', () => {
    render(<LegalDocForm />);
    // Input with label containing "Title"
    expect(screen.getByRole('textbox', { name: /title/i })).toBeInTheDocument();
  });

  it('renders version input with default 1.0', () => {
    render(<LegalDocForm />);
    const versionInput = screen.getByRole('textbox', { name: /version/i });
    expect(versionInput).toHaveValue('1.0');
  });

  it('shows validation error toast when submitting without a title', async () => {
    const user = userEvent.setup();
    render(<LegalDocForm />);

    // Find Save/Create button
    const saveBtn = screen.getByRole('button', { name: /create document/i });
    await user.click(saveBtn);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
    expect(adminLegalDocs.create).not.toHaveBeenCalled();
  });

  it('calls adminLegalDocs.create on valid submit and navigates away', async () => {
    const user = userEvent.setup();
    vi.mocked(adminLegalDocs.create).mockResolvedValue({ success: true, data: { id: 99 } });

    render(<LegalDocForm />);

    await user.type(screen.getByRole('textbox', { name: /title/i }), 'Terms of Service');

    const saveBtn = screen.getByRole('button', { name: /create document/i });
    await user.click(saveBtn);

    await waitFor(() => {
      expect(adminLegalDocs.create).toHaveBeenCalledWith(
        expect.objectContaining({ title: 'Terms of Service' }),
      );
    });
    expect(mockToast.success).toHaveBeenCalled();
    expect(mockNavigate).toHaveBeenCalled();
  });

  it('shows error toast when create API fails', async () => {
    const user = userEvent.setup();
    vi.mocked(adminLegalDocs.create).mockResolvedValue({ success: false, error: 'Save failed' });

    render(<LegalDocForm />);

    await user.type(screen.getByRole('textbox', { name: /title/i }), 'Terms of Service');
    await user.click(screen.getByRole('button', { name: /create document/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
    expect(mockNavigate).not.toHaveBeenCalled();
  });

  it('shows error toast when create API throws', async () => {
    const user = userEvent.setup();
    vi.mocked(adminLegalDocs.create).mockRejectedValue(new Error('Network'));

    render(<LegalDocForm />);

    await user.type(screen.getByRole('textbox', { name: /title/i }), 'Terms of Service');
    await user.click(screen.getByRole('button', { name: /create document/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('navigates back when Cancel is pressed', async () => {
    const user = userEvent.setup();
    render(<LegalDocForm />);

    await user.click(screen.getByRole('button', { name: /cancel/i }));
    expect(mockNavigate).toHaveBeenCalled();
  });
});

describe('LegalDocForm — edit mode (id=42)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockParamsId = '42'; // edit mode
  });

  it('shows a loading spinner while fetching the document', () => {
    vi.mocked(adminLegalDocs.get).mockReturnValue(new Promise(() => {}));
    render(<LegalDocForm />);

    const spinners = screen.getAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeInTheDocument();
  });

  it('pre-fills form with fetched document data', async () => {
    vi.mocked(adminLegalDocs.get).mockResolvedValue({
      success: true,
      data: EXISTING_DOC,
    });

    render(<LegalDocForm />);

    await waitFor(() => {
      expect(screen.getByRole('textbox', { name: /title/i })).toHaveValue('Privacy Policy');
    });

    expect(screen.getByRole('textbox', { name: /version/i })).toHaveValue('2.0');
  });

  it('shows error toast if document fetch fails', async () => {
    vi.mocked(adminLegalDocs.get).mockRejectedValue(new Error('Not found'));
    render(<LegalDocForm />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('calls adminLegalDocs.update on save with correct id', async () => {
    const user = userEvent.setup();
    vi.mocked(adminLegalDocs.get).mockResolvedValue({
      success: true,
      data: EXISTING_DOC,
    });
    vi.mocked(adminLegalDocs.update).mockResolvedValue({ success: true });

    render(<LegalDocForm />);

    await waitFor(() => {
      expect(screen.getByRole('textbox', { name: /title/i })).toHaveValue('Privacy Policy');
    });

    const saveBtn = screen.getByRole('button', { name: /update document/i });
    await user.click(saveBtn);

    await waitFor(() => {
      expect(adminLegalDocs.update).toHaveBeenCalledWith(
        42,
        expect.objectContaining({ title: 'Privacy Policy' }),
      );
    });
    expect(mockToast.success).toHaveBeenCalled();
    expect(mockNavigate).toHaveBeenCalled();
  });
});
