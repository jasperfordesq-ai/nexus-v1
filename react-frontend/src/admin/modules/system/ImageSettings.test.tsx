// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── mock contexts ────────────────────────────────────────────────────────────

const mockToast = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }));

vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast }),
);

// ── mock adminApi ────────────────────────────────────────────────────────────

const mockGetImageSettings = vi.hoisted(() => vi.fn());
const mockUpdateImageSettings = vi.hoisted(() => vi.fn());

vi.mock('@/admin/api/adminApi', () => ({
  adminSettings: {
    getImageSettings: mockGetImageSettings,
    updateImageSettings: mockUpdateImageSettings,
  },
}));

// ── mock hooks ───────────────────────────────────────────────────────────────

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ── component ────────────────────────────────────────────────────────────────

import { ImageSettings } from './ImageSettings';

const API_DATA = {
  max_file_size: '10',
  max_width: '4096',
  max_height: '4096',
  allowed_formats: 'jpg, jpeg, png',
  auto_resize: false,
  auto_webp: true,
  strip_exif: false,
  generate_thumbnails: true,
};

describe('ImageSettings', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows a loading spinner while fetching', () => {
    mockGetImageSettings.mockReturnValue(new Promise(() => {}));
    render(<ImageSettings />);

    const spinner = screen.getAllByRole('status').find(
      (el) => el.getAttribute('aria-busy') === 'true',
    );
    expect(spinner).toBeDefined();
  });

  it('renders form fields with API values after load', async () => {
    mockGetImageSettings.mockResolvedValue({ success: true, data: API_DATA });
    render(<ImageSettings />);

    await waitFor(() => {
      // File size input should carry the API value
      const inputs = screen.getAllByRole('spinbutton');
      const fileSizeInput = inputs.find((i) => (i as HTMLInputElement).value === '10');
      expect(fileSizeInput).toBeInTheDocument();
    });
  });

  it('shows default form values when API returns no data', async () => {
    mockGetImageSettings.mockResolvedValue({ success: true, data: null });
    render(<ImageSettings />);

    await waitFor(() => {
      // Default max_file_size = '5'
      const inputs = screen.getAllByRole('spinbutton');
      const defaultInput = inputs.find((i) => (i as HTMLInputElement).value === '5');
      expect(defaultInput).toBeInTheDocument();
    });
  });

  it('calls toast.error when API rejects', async () => {
    mockGetImageSettings.mockRejectedValue(new Error('network'));
    render(<ImageSettings />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('calls updateImageSettings and shows success toast on save', async () => {
    mockGetImageSettings.mockResolvedValue({ success: true, data: API_DATA });
    mockUpdateImageSettings.mockResolvedValue({ success: true });

    render(<ImageSettings />);

    // Wait for the form to appear
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument();
    });

    const saveBtn = screen.getByRole('button', { name: /save/i });
    await userEvent.click(saveBtn);

    await waitFor(() => {
      expect(mockUpdateImageSettings).toHaveBeenCalled();
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when save returns success=false', async () => {
    mockGetImageSettings.mockResolvedValue({ success: true, data: API_DATA });
    mockUpdateImageSettings.mockResolvedValue({ success: false, error: 'Bad request' });

    render(<ImageSettings />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument();
    });

    await userEvent.click(screen.getByRole('button', { name: /save/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('renders toggle switches for processing options', async () => {
    mockGetImageSettings.mockResolvedValue({ success: true, data: API_DATA });
    render(<ImageSettings />);

    await waitFor(() => {
      // HeroUI Switch renders as role=switch
      const switches = screen.getAllByRole('switch');
      expect(switches.length).toBeGreaterThanOrEqual(4);
    });
  });
});
