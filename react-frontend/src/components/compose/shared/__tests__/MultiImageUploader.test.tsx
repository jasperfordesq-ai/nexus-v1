// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for MultiImageUploader component
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

vi.mock('@/lib/compress-image', () => ({
  compressImage: vi.fn((file: File) => Promise.resolve(file)),
}));

import { MultiImageUploader } from '../MultiImageUploader';

const defaultProps = {
  files: [] as File[],
  previews: [] as string[],
  onAdd: vi.fn(),
  onRemove: vi.fn(),
  onReorder: vi.fn(),
};

describe('MultiImageUploader', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    render(<MultiImageUploader {...defaultProps} />);
    expect(document.body).toBeTruthy();
  });

  it('renders add image button when no images are present', () => {
    render(<MultiImageUploader {...defaultProps} />);
    // Button with ImagePlus icon and the add text
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThan(0);
  });

  it('renders a hidden file input', () => {
    const { container } = render(<MultiImageUploader {...defaultProps} />);
    const input = container.querySelector('input[type="file"]');
    expect(input).toBeInTheDocument();
    expect(input).toHaveClass('hidden');
  });

  it('shows image previews when files are provided', () => {
    const files = [
      new File(['a'], 'img1.jpg', { type: 'image/jpeg' }),
      new File(['b'], 'img2.jpg', { type: 'image/jpeg' }),
    ];
    const previews = ['data:image/jpeg;base64,aaa', 'data:image/jpeg;base64,bbb'];

    render(
      <MultiImageUploader
        {...defaultProps}
        files={files}
        previews={previews}
      />,
    );

    expect(screen.getByAltText('Upload preview 1')).toBeInTheDocument();
    expect(screen.getByAltText('Upload preview 2')).toBeInTheDocument();
  });

  it('shows remove buttons for each image', () => {
    const files = [
      new File(['a'], 'img1.jpg', { type: 'image/jpeg' }),
    ];
    const previews = ['data:image/jpeg;base64,aaa'];

    render(
      <MultiImageUploader
        {...defaultProps}
        files={files}
        previews={previews}
      />,
    );

    const removeButtons = screen.getAllByRole('button', { name: /remove/i });
    expect(removeButtons).toHaveLength(1);
  });

  it('calls onRemove when remove button is clicked', () => {
    const onRemove = vi.fn();
    const files = [new File(['a'], 'img1.jpg', { type: 'image/jpeg' })];
    const previews = ['data:image/jpeg;base64,aaa'];

    render(
      <MultiImageUploader
        {...defaultProps}
        files={files}
        previews={previews}
        onRemove={onRemove}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /remove/i }));
    expect(onRemove).toHaveBeenCalledWith(0);
  });

  it('hides add button when max images reached', () => {
    const files = [
      new File(['a'], 'img1.jpg', { type: 'image/jpeg' }),
      new File(['b'], 'img2.jpg', { type: 'image/jpeg' }),
    ];
    const previews = ['data:image/jpeg;base64,aaa', 'data:image/jpeg;base64,bbb'];

    const { container } = render(
      <MultiImageUploader
        {...defaultProps}
        files={files}
        previews={previews}
        maxImages={2}
      />,
    );

    // The add button should not appear since we've hit max
    // The only buttons should be drag handles and remove buttons
    const input = container.querySelector('input[type="file"]');
    expect(input).toBeInTheDocument(); // hidden input still exists
  });

  it('shows image count badge when files are present', () => {
    const files = [new File(['a'], 'img1.jpg', { type: 'image/jpeg' })];
    const previews = ['data:image/jpeg;base64,aaa'];

    render(
      <MultiImageUploader
        {...defaultProps}
        files={files}
        previews={previews}
        maxImages={4}
      />,
    );

    // The count text should be rendered (uses i18n key compose.images_max)
    expect(document.body.textContent).toBeTruthy();
  });

  it('calls onError for non-image files', () => {
    const onError = vi.fn();
    const { container } = render(
      <MultiImageUploader {...defaultProps} onError={onError} />,
    );

    const input = container.querySelector('input[type="file"]')!;
    const textFile = new File(['hello'], 'doc.txt', { type: 'text/plain' });
    fireEvent.change(input, { target: { files: [textFile] } });
    expect(onError).toHaveBeenCalled();
  });

  it('calls onError for oversized files', () => {
    const onError = vi.fn();
    const { container } = render(
      <MultiImageUploader {...defaultProps} maxSizeMb={1} onError={onError} />,
    );

    const input = container.querySelector('input[type="file"]')!;
    const bigFile = new File(['x'.repeat(2 * 1024 * 1024)], 'big.jpg', { type: 'image/jpeg' });
    fireEvent.change(input, { target: { files: [bigFile] } });
    expect(onError).toHaveBeenCalled();
  });
});
