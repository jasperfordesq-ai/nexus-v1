// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ImageUploader component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';

vi.mock('@/contexts', () => ({
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
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test', tagline: null }, branding: { name: 'Test', logo_url: null }, tenantSlug: 'test', tenantPath: (p: string) => '/test' + p, isLoading: false, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() })),
}));

import { ImageUploader } from '../ImageUploader';

const defaultProps = {
  file: null,
  preview: null,
  onSelect: vi.fn(),
  onRemove: vi.fn(),
};

describe('ImageUploader', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    render(<ImageUploader {...defaultProps} />);
    expect(document.body).toBeTruthy();
  });

  it('renders add image button when no file is selected', () => {
    render(<ImageUploader {...defaultProps} />);
    // The button text comes from t('compose.image_add') which is the i18n key
    const addBtn = screen.getByRole('button');
    expect(addBtn).toBeInTheDocument();
  });

  it('renders a hidden file input', () => {
    const { container } = render(<ImageUploader {...defaultProps} />);
    const input = container.querySelector('input[type="file"]');
    expect(input).toBeInTheDocument();
    expect(input).toHaveClass('hidden');
  });

  it('accepts correct image types', () => {
    const { container } = render(<ImageUploader {...defaultProps} />);
    const input = container.querySelector('input[type="file"]');
    expect(input).toHaveAttribute('accept', 'image/jpeg,image/png,image/gif,image/webp');
  });

  it('shows image preview when preview prop is provided', () => {
    render(
      <ImageUploader
        {...defaultProps}
        file={new File(['test'], 'photo.jpg', { type: 'image/jpeg' })}
        preview="data:image/jpeg;base64,abc123"
      />,
    );
    const img = screen.getByAltText('Upload preview');
    expect(img).toBeInTheDocument();
    expect(img).toHaveAttribute('src', 'data:image/jpeg;base64,abc123');
  });

  it('shows remove button when preview is displayed', () => {
    render(
      <ImageUploader
        {...defaultProps}
        file={new File(['test'], 'photo.jpg', { type: 'image/jpeg' })}
        preview="data:image/jpeg;base64,abc123"
      />,
    );
    const removeBtn = screen.getByRole('button', { name: /remove/i });
    expect(removeBtn).toBeInTheDocument();
  });

  it('calls onRemove when remove button is clicked', () => {
    const onRemove = vi.fn();
    render(
      <ImageUploader
        {...defaultProps}
        onRemove={onRemove}
        file={new File(['test'], 'photo.jpg', { type: 'image/jpeg' })}
        preview="data:image/jpeg;base64,abc123"
      />,
    );
    fireEvent.click(screen.getByRole('button', { name: /remove/i }));
    expect(onRemove).toHaveBeenCalled();
  });

  it('shows file name and size when a file is selected', () => {
    const file = new File(['x'.repeat(1024 * 1024)], 'photo.jpg', { type: 'image/jpeg' });
    render(
      <ImageUploader
        {...defaultProps}
        file={file}
        preview="data:image/jpeg;base64,abc123"
      />,
    );
    expect(screen.getByText(/photo\.jpg/)).toBeInTheDocument();
    expect(screen.getByText(/1\.0MB/)).toBeInTheDocument();
  });

  it('calls onError when a non-image file is selected', () => {
    const onError = vi.fn();
    const { container } = render(
      <ImageUploader {...defaultProps} onError={onError} />,
    );
    const input = container.querySelector('input[type="file"]')!;
    const textFile = new File(['hello'], 'doc.txt', { type: 'text/plain' });
    fireEvent.change(input, { target: { files: [textFile] } });
    expect(onError).toHaveBeenCalled();
  });

  it('calls onError when file exceeds max size', () => {
    const onError = vi.fn();
    const { container } = render(
      <ImageUploader {...defaultProps} maxSizeMb={1} onError={onError} />,
    );
    const input = container.querySelector('input[type="file"]')!;
    // Create a file larger than 1MB
    const bigFile = new File(['x'.repeat(2 * 1024 * 1024)], 'big.jpg', { type: 'image/jpeg' });
    fireEvent.change(input, { target: { files: [bigFile] } });
    expect(onError).toHaveBeenCalled();
  });
});
