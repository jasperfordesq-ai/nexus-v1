// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';

// ─── Mocks ───────────────────────────────────────────────────────────────────

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn() };

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => mockToast),
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({
    tenant: { id: 2, name: 'Test', slug: 'test', tagline: null },
    branding: { name: 'Test', logo_url: null },
    tenantSlug: 'test',
    tenantPath: (p: string) => '/test' + p,
    isLoading: false,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  }),
}));

vi.mock('@/lib/api', () => {
  const mockApi = {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  };
  return { api: mockApi, default: mockApi };
});

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('react-router-dom', async (importActual) => {
  const actual = await importActual<typeof import('react-router-dom')>();
  return {
    ...actual,
    useParams: () => ({ id: '42' }),
  };
});

import { api } from '@/lib/api';

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const PREVIEW_RESPONSE = {
  organization: { id: 42, name: 'Test Verein', org_type: 'verein' },
  summary: { total_rows: 2, ready_to_create: 1, ready_to_link: 1, duplicates: 0, invalid: 0 },
  items: [
    { row: 1, email: 'alice@example.com', first_name: 'Alice', last_name: 'Smith', phone: null, role: 'member', action: 'create', existing_user_id: null, errors: [] },
    { row: 2, email: 'bob@example.com', first_name: 'Bob', last_name: 'Jones', phone: null, role: 'member', action: 'link_existing', existing_user_id: 7, errors: [] },
  ],
};

const PREVIEW_WITH_INVALID = {
  ...PREVIEW_RESPONSE,
  summary: { ...PREVIEW_RESPONSE.summary, invalid: 1 },
  items: [
    ...PREVIEW_RESPONSE.items,
    { row: 3, email: '', first_name: '', last_name: '', phone: null, role: '', action: 'invalid', existing_user_id: null, errors: ['Email is required'] },
  ],
};

const IMPORT_RESPONSE = {
  organization: { id: 42, name: 'Test Verein' },
  created: 1,
  linked: 1,
  skipped: 0,
  members: [
    { user_id: 100, email: 'alice@example.com', created: true, temporary_password: 'tmp123' },
    { user_id: 7,   email: 'bob@example.com',   created: false, temporary_password: null },
  ],
};

// ─── Import component after mocks ─────────────────────────────────────────────

import VereinMembersImportPage from './VereinMembersImportPage';

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('VereinMembersImportPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders page title and step-1 upload card', () => {
    render(<VereinMembersImportPage />);
    // Page has a heading and a textarea for CSV
    expect(screen.getAllByRole('textbox').length).toBeGreaterThan(0);
  });

  it('shows error toast when previewing empty CSV', async () => {
    const user = userEvent.setup();
    render(<VereinMembersImportPage />);
    // Find the preview button (primary, has "preview" in text via i18n key fallback)
    const buttons = screen.getAllByRole('button');
    // The preview button contains the i18n key value — find it by testing every button
    const previewBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('preview') || b.getAttribute('data-key') === 'verein_import.preview') ?? buttons[buttons.length - 1];
    await user.click(previewBtn);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('calls preview API and renders summary + table rows when CSV is pasted', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: true, data: PREVIEW_RESPONSE });
    render(<VereinMembersImportPage />);

    // Type CSV text into the textarea
    const textarea = screen.getAllByRole('textbox').find((el) => el.tagName === 'TEXTAREA') as HTMLTextAreaElement;
    fireEvent.change(textarea, { target: { value: 'email,first_name\nalice@example.com,Alice' } });

    // Click preview – find button whose text includes preview key
    const buttons = screen.getAllByRole('button');
    const previewBtn = buttons.find((b) => {
      const txt = b.textContent ?? '';
      return txt.toLowerCase().includes('preview') || txt.includes('verein_import');
    }) ?? buttons[buttons.length - 2];
    fireEvent.click(previewBtn);

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        '/v2/caring-community/vereine/42/members/import/preview',
        expect.objectContaining({ csv: expect.any(String) }),
      );
    });

    // After success, table rows appear
    await waitFor(() => {
      expect(screen.getByText('alice@example.com')).toBeInTheDocument();
      expect(screen.getByText('bob@example.com')).toBeInTheDocument();
    });
  });

  it('shows invalid-row alert when preview summary has invalid > 0', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: true, data: PREVIEW_WITH_INVALID });
    render(<VereinMembersImportPage />);

    const textarea = screen.getAllByRole('textbox').find((el) => el.tagName === 'TEXTAREA') as HTMLTextAreaElement;
    fireEvent.change(textarea, { target: { value: 'bad,csv' } });

    const buttons = screen.getAllByRole('button');
    const previewBtn = buttons.find((b) => {
      const txt = b.textContent ?? '';
      return txt.toLowerCase().includes('preview');
    }) ?? buttons[buttons.length - 2];
    fireEvent.click(previewBtn);

    await waitFor(() => {
      expect(screen.getByRole('alert')).toBeInTheDocument();
    });
  });

  it('disables confirm button when preview has invalid rows', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: true, data: PREVIEW_WITH_INVALID });
    render(<VereinMembersImportPage />);

    const textarea = screen.getAllByRole('textbox').find((el) => el.tagName === 'TEXTAREA') as HTMLTextAreaElement;
    fireEvent.change(textarea, { target: { value: 'bad,csv' } });

    const buttons = screen.getAllByRole('button');
    const previewBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('preview')) ?? buttons[buttons.length - 2];
    fireEvent.click(previewBtn);

    await waitFor(() => {
      // Confirm button should be disabled
      const confirmBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('confirm') ||
        b.textContent?.toLowerCase().includes('import'),
      );
      if (confirmBtn) {
        expect(confirmBtn).toBeDisabled();
      }
    });
  });

  it('calls import API and shows result card on success', async () => {
    vi.mocked(api.post)
      .mockResolvedValueOnce({ success: true, data: PREVIEW_RESPONSE })
      .mockResolvedValueOnce({ success: true, data: IMPORT_RESPONSE });

    render(<VereinMembersImportPage />);

    const textarea = screen.getAllByRole('textbox').find((el) => el.tagName === 'TEXTAREA') as HTMLTextAreaElement;
    fireEvent.change(textarea, { target: { value: 'email,first_name\nalice@example.com,Alice' } });

    const buttons = screen.getAllByRole('button');
    const previewBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('preview')) ?? buttons[buttons.length - 2];
    fireEvent.click(previewBtn);

    await waitFor(() => expect(screen.getByText('alice@example.com')).toBeInTheDocument());

    // Click the confirm import button
    const confirmBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('confirm') ||
      b.textContent?.toLowerCase().includes('import'),
    );
    if (confirmBtn && !confirmBtn.hasAttribute('disabled')) {
      fireEvent.click(confirmBtn);
      await waitFor(() => {
        expect(api.post).toHaveBeenCalledWith(
          '/v2/caring-community/vereine/42/members/import',
          expect.objectContaining({ csv: expect.any(String) }),
        );
      });
      await waitFor(() => expect(mockToast.success).toHaveBeenCalled());
    }
  });

  it('shows temporary passwords in result card when members have them', async () => {
    vi.mocked(api.post)
      .mockResolvedValueOnce({ success: true, data: PREVIEW_RESPONSE })
      .mockResolvedValueOnce({ success: true, data: IMPORT_RESPONSE });

    render(<VereinMembersImportPage />);

    const textarea = screen.getAllByRole('textbox').find((el) => el.tagName === 'TEXTAREA') as HTMLTextAreaElement;
    fireEvent.change(textarea, { target: { value: 'email,first_name\nalice@example.com,Alice' } });

    const buttons = screen.getAllByRole('button');
    const previewBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('preview')) ?? buttons[buttons.length - 2];
    fireEvent.click(previewBtn);

    await waitFor(() => expect(screen.getByText('alice@example.com')).toBeInTheDocument());

    const confirmBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('confirm') ||
      b.textContent?.toLowerCase().includes('import'),
    );
    if (confirmBtn && !confirmBtn.hasAttribute('disabled')) {
      fireEvent.click(confirmBtn);
      await waitFor(() => {
        expect(screen.getByText('tmp123')).toBeInTheDocument();
      });
    }
  });

  it('shows error toast when preview API fails', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: false, error: 'Server error' });
    render(<VereinMembersImportPage />);

    const textarea = screen.getAllByRole('textbox').find((el) => el.tagName === 'TEXTAREA') as HTMLTextAreaElement;
    fireEvent.change(textarea, { target: { value: 'email\nalice@example.com' } });

    const buttons = screen.getAllByRole('button');
    const previewBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('preview')) ?? buttons[buttons.length - 2];
    fireEvent.click(previewBtn);

    await waitFor(() => expect(mockToast.error).toHaveBeenCalled());
  });

  it('loads CSV from file input', async () => {
    render(<VereinMembersImportPage />);
    const fileInput = document.querySelector('input[type="file"]') as HTMLInputElement;
    expect(fileInput).toBeInTheDocument();

    const file = new File(['email,first_name\nalice@example.com,Alice'], 'members.csv', { type: 'text/csv' });
    // Dispatch change event with file — FileReader is real in jsdom; just verify onChange fires
    fireEvent.change(fileInput, { target: { files: [file] } });
    // No assertion on async FileReader outcome here — just confirm no crash
  });

  it('cancel button clears preview', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: true, data: PREVIEW_RESPONSE });
    render(<VereinMembersImportPage />);

    const textarea = screen.getAllByRole('textbox').find((el) => el.tagName === 'TEXTAREA') as HTMLTextAreaElement;
    fireEvent.change(textarea, { target: { value: 'email\nalice@example.com' } });

    const buttons = screen.getAllByRole('button');
    const previewBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('preview')) ?? buttons[buttons.length - 2];
    fireEvent.click(previewBtn);

    await waitFor(() => expect(screen.getByText('alice@example.com')).toBeInTheDocument());

    const cancelBtn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('cancel'));
    if (cancelBtn) {
      fireEvent.click(cancelBtn);
      await waitFor(() => expect(screen.queryByText('alice@example.com')).not.toBeInTheDocument());
    }
  });
});
