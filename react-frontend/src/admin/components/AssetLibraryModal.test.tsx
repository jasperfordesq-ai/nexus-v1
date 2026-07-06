// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it, vi, beforeEach } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import { AssetLibraryModal } from './AssetLibraryModal';

const { mockAdminBuilderAssets } = vi.hoisted(() => ({
  mockAdminBuilderAssets: {
    listImages: vi.fn(),
    uploadImage: vi.fn(),
  },
}));

const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  warning: vi.fn(),
  info: vi.fn(),
};

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  }),
);

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('../api/adminApi', () => ({
  adminBuilderAssets: mockAdminBuilderAssets,
}));

const labels = {
  title: 'Image library',
  upload: 'Upload new',
  empty: 'No images yet',
  loadFailed: 'Could not load images',
  uploadFailed: 'Image upload failed',
};

describe('AssetLibraryModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockAdminBuilderAssets.listImages.mockResolvedValue({
      success: true,
      data: { images: [] },
    });
  });

  it('loads library images and selects an absolute image URL', async () => {
    const onSelect = vi.fn();
    const onClose = vi.fn();
    mockAdminBuilderAssets.listImages.mockResolvedValueOnce({
      success: true,
      data: {
        images: [
          {
            url: 'https://cdn.example.test/uploads/library.png',
            path: 'uploads/library.png',
            name: 'Library image',
          },
        ],
      },
    });

    render(
      <AssetLibraryModal
        isOpen
        onClose={onClose}
        onSelect={onSelect}
        labels={labels}
      />,
    );

    const imageButton = await screen.findByRole('button', { name: 'Library image' });
    fireEvent.click(imageButton);

    expect(onSelect).toHaveBeenCalledWith('https://cdn.example.test/uploads/library.png');
    expect(onClose).toHaveBeenCalled();
  });

  it('uploads a new image through the shared builder asset API', async () => {
    const onSelect = vi.fn();
    const onClose = vi.fn();
    mockAdminBuilderAssets.uploadImage.mockResolvedValueOnce({
      success: true,
      data: { url: 'https://cdn.example.test/uploads/new.png', path: 'uploads/new.png' },
    });

    const { container } = render(
      <AssetLibraryModal
        isOpen
        onClose={onClose}
        onSelect={onSelect}
        labels={labels}
      />,
    );
    await waitFor(() => expect(mockAdminBuilderAssets.listImages).toHaveBeenCalled());

    const fileInput = container.querySelector('input[type="file"]') as HTMLInputElement;
    fireEvent.change(fileInput, {
      target: { files: [new File(['img'], 'new.png', { type: 'image/png' })] },
    });

    await waitFor(() => expect(mockAdminBuilderAssets.uploadImage).toHaveBeenCalled());
    expect(onSelect).toHaveBeenCalledWith('https://cdn.example.test/uploads/new.png');
    expect(onClose).toHaveBeenCalled();
  });
});
