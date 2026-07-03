// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for LegalDocVersionEditor — the full-page version editor that replaced
 * the old unscrollable modal.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ─── Admin API mock ────────────────────────────────────────────────────────────
const { mockAdminLegalDocs } = vi.hoisted(() => ({
  mockAdminLegalDocs: {
    get: vi.fn(),
    getVersions: vi.fn(),
    createVersion: vi.fn(),
    updateVersion: vi.fn(),
  },
}));

vi.mock('../../api/adminApi', () => ({
  adminLegalDocs: mockAdminLegalDocs,
}));

// ─── Mock the heavy Lexical editor as a plain textarea ─────────────────────────
vi.mock('@/admin/components', () => ({
  LegalDocEditor: ({ value, onChange }: { value: string; onChange: (html: string) => void }) => (
    <textarea
      aria-label="content-editor"
      value={value}
      onChange={(e) => onChange(e.target.value)}
    />
  ),
}));

vi.mock('../../AdminMetaContext', () => ({ useAdminPageMeta: vi.fn() }));

// ─── Router ────────────────────────────────────────────────────────────────────
const mockNavigate = vi.fn();
let mockParams: { id?: string; versionId?: string } = { id: '5' };

vi.mock('react-router-dom', async (importOriginal) => {
  const orig = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...orig,
    useParams: () => mockParams,
    useNavigate: () => mockNavigate,
  };
});

// ─── Contexts ──────────────────────────────────────────────────────────────────
const mockSuccess = vi.fn();
const mockError = vi.fn();

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => ({ success: mockSuccess, error: mockError, info: vi.fn(), warning: vi.fn() }),
    useTenant: () => ({
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
      tenant: { id: 2, name: 'Test', slug: 'test' },
    }),
  })
);

const DOC = { id: 5, title: 'Terms', type: 'terms' };

async function renderEditor() {
  const Editor = (await import('./LegalDocVersionEditor')).default;
  return render(<Editor />);
}

describe('LegalDocVersionEditor — create mode', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockParams = { id: '5' };
    mockAdminLegalDocs.get.mockResolvedValue({ success: true, data: DOC });
  });

  it('blocks submit and does not call the API when required fields are empty', async () => {
    await renderEditor();

    await waitFor(() => screen.getByRole('button', { name: /create|save/i }));
    const saveBtn = screen.getAllByRole('button').find((b) => /create|save/i.test(b.textContent || ''));
    fireEvent.click(saveBtn!);

    await waitFor(() => {
      expect(mockAdminLegalDocs.createVersion).not.toHaveBeenCalled();
    });
  });

  it('calls createVersion with the entered content on a valid submit', async () => {
    mockAdminLegalDocs.createVersion.mockResolvedValue({ success: true, data: { id: 10 } });
    await renderEditor();

    await waitFor(() => screen.getByRole('textbox', { name: 'content-editor' }));

    fireEvent.change(screen.getByRole('textbox', { name: /version number/i }), { target: { value: '1.0' } });
    fireEvent.change(screen.getByRole('textbox', { name: 'content-editor' }), { target: { value: '<p>Body</p>' } });
    // effective_date is a date input (not a textbox role)
    const dateInput = document.querySelector('input[type="date"]') as HTMLInputElement;
    fireEvent.change(dateInput, { target: { value: '2026-05-01' } });

    const saveBtn = screen.getAllByRole('button').find((b) => /create|save/i.test(b.textContent || ''));
    fireEvent.click(saveBtn!);

    await waitFor(() => {
      expect(mockAdminLegalDocs.createVersion).toHaveBeenCalledWith(
        5,
        expect.objectContaining({ version_number: '1.0', content: '<p>Body</p>', effective_date: '2026-05-01' }),
      );
    });
    expect(mockNavigate).toHaveBeenCalledWith(expect.stringContaining('/legal-documents/5/versions'));
  });
});

describe('LegalDocVersionEditor — edit mode', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockParams = { id: '5', versionId: '2' };
    mockAdminLegalDocs.get.mockResolvedValue({ success: true, data: DOC });
  });

  it('loads the draft and calls updateVersion on save', async () => {
    mockAdminLegalDocs.getVersions.mockResolvedValue({
      success: true,
      data: [{ id: 2, version_number: '2.0', content: '<p>Draft</p>', effective_date: '2026-04-01', is_draft: true }],
    });
    mockAdminLegalDocs.updateVersion.mockResolvedValue({ success: true });

    await renderEditor();

    await waitFor(() => {
      expect(screen.getByRole('textbox', { name: /version number/i })).toHaveValue('2.0');
    });

    const saveBtn = screen.getAllByRole('button').find((b) => /update|save/i.test(b.textContent || ''));
    fireEvent.click(saveBtn!);

    await waitFor(() => {
      expect(mockAdminLegalDocs.updateVersion).toHaveBeenCalledWith(5, 2, expect.objectContaining({ version_number: '2.0' }));
    });
  });

  it('redirects with an error when the target version is already published', async () => {
    mockAdminLegalDocs.getVersions.mockResolvedValue({
      success: true,
      data: [{ id: 2, version_number: '2.0', content: '<p>Published</p>', effective_date: '2026-04-01', is_draft: false }],
    });

    await renderEditor();

    await waitFor(() => {
      expect(mockError).toHaveBeenCalled();
      expect(mockNavigate).toHaveBeenCalledWith(expect.stringContaining('/versions'));
    });
    expect(mockAdminLegalDocs.updateVersion).not.toHaveBeenCalled();
  });

  it('redirects with an error when the version cannot be found', async () => {
    mockAdminLegalDocs.getVersions.mockResolvedValue({ success: true, data: [] });

    await renderEditor();

    await waitFor(() => {
      expect(mockError).toHaveBeenCalled();
      expect(mockNavigate).toHaveBeenCalledWith(expect.stringContaining('/versions'));
    });
  });
});
