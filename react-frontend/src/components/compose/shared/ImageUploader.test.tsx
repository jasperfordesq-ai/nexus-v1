// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── API mock (not used by this component but required pattern) ───────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    download: vi.fn(),
    upload: vi.fn(),
  },
}));
vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));

// ─── Contexts ─────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts());

// ─── Stub HeroUI Button so onPress fires reliably in jsdom ───────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    Button: ({
      children,
      onPress,
      'aria-label': ariaLabel,
      startContent,
      isIconOnly,
      ...rest
    }: {
      children?: React.ReactNode;
      onPress?: () => void;
      'aria-label'?: string;
      startContent?: React.ReactNode;
      isIconOnly?: boolean;
      [key: string]: unknown;
    }) => (
      <button
        aria-label={ariaLabel}
        onClick={() => onPress?.()}
        {...(isIconOnly ? { 'data-icon-only': 'true' } : {})}
        {...rest}
      >
        {startContent}
        {children}
      </button>
    ),
  };
});

// ─── Stub FileReader so readAsDataURL calls onload synchronously ──────────────
const { MockFileReader } = vi.hoisted(() => {
  class MockFileReader {
    onload: ((ev: { target: { result: string } }) => void) | null = null;
    result: string | null = null;

    readAsDataURL(_file: File) {
      this.result = 'data:image/png;base64,FAKE';
      this.onload?.({ target: { result: this.result } });
    }
  }
  return { MockFileReader };
});

// ─────────────────────────────────────────────────────────────────────────────
describe('ImageUploader', () => {
  const onSelect = vi.fn();
  const onRemove = vi.fn();
  const onError = vi.fn();

  beforeEach(() => {
    vi.resetAllMocks();
    // Patch FileReader on window
    (global as unknown as { FileReader: typeof MockFileReader }).FileReader = MockFileReader as unknown as typeof MockFileReader;
  });

  const defaultProps = {
    file: null,
    preview: null,
    onSelect,
    onRemove,
    onError,
  };

  // ── Render states ──────────────────────────────────────────────────────────

  it('renders the add image button when no file is selected', async () => {
    const { ImageUploader } = await import('./ImageUploader');
    render(<ImageUploader {...defaultProps} />);
    // Button text comes from i18n key compose.image_add
    const btn = screen.getByRole('button');
    expect(btn).toBeInTheDocument();
  });

  it('shows a preview image when preview prop is provided', async () => {
    const { ImageUploader } = await import('./ImageUploader');
    render(<ImageUploader {...defaultProps} file={new File(['x'], 'photo.png', { type: 'image/png' })} preview="blob:http://localhost/abc" />);
    const img = screen.getByRole('img');
    expect(img).toBeInTheDocument();
    expect(img.getAttribute('src')).toBe('blob:http://localhost/abc');
  });

  it('does not show a preview image when preview is null', async () => {
    const { ImageUploader } = await import('./ImageUploader');
    render(<ImageUploader {...defaultProps} />);
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
  });

  it('shows the remove button when a preview is visible', async () => {
    const { ImageUploader } = await import('./ImageUploader');
    render(<ImageUploader {...defaultProps} file={new File(['x'], 'p.png', { type: 'image/png' })} preview="blob:x" />);
    const removeBtn = screen.getByRole('button', { name: /remove|delete/i });
    // If i18n key resolves to something else, fall back to aria-label check
    expect(removeBtn || screen.getAllByRole('button').find((b) => b.getAttribute('aria-label'))).toBeDefined();
  });

  it('shows file name when a file is selected', async () => {
    const { ImageUploader } = await import('./ImageUploader');
    render(
      <ImageUploader
        {...defaultProps}
        file={new File(['x'], 'holiday.png', { type: 'image/png' })}
        preview="blob:x"
      />,
    );
    // File name appears in the size label span
    expect(screen.getByText(/holiday\.png/)).toBeInTheDocument();
  });

  // ── File selection ─────────────────────────────────────────────────────────

  it('calls onSelect with file and data-url when a valid image is chosen', async () => {
    const { ImageUploader } = await import('./ImageUploader');
    render(<ImageUploader {...defaultProps} />);
    const input = document.querySelector('input[type="file"]') as HTMLInputElement;
    expect(input).not.toBeNull();
    const file = new File(['content'], 'avatar.png', { type: 'image/png' });
    fireEvent.change(input, { target: { files: [file] } });
    await waitFor(() => {
      expect(onSelect).toHaveBeenCalledWith(file, 'data:image/png;base64,FAKE');
    });
  });

  it('calls onError and does not call onSelect when file is not an image', async () => {
    const { ImageUploader } = await import('./ImageUploader');
    render(<ImageUploader {...defaultProps} />);
    const input = document.querySelector('input[type="file"]') as HTMLInputElement;
    const textFile = new File(['hello'], 'doc.txt', { type: 'text/plain' });
    fireEvent.change(input, { target: { files: [textFile] } });
    expect(onError).toHaveBeenCalled();
    expect(onSelect).not.toHaveBeenCalled();
  });

  it('calls onError and does not call onSelect when file exceeds maxSizeMb', async () => {
    const { ImageUploader } = await import('./ImageUploader');
    render(<ImageUploader {...defaultProps} maxSizeMb={1} />);
    const input = document.querySelector('input[type="file"]') as HTMLInputElement;
    // Create a file object whose size property exceeds 1 MB
    const bigFile = new File([new Uint8Array(2 * 1024 * 1024)], 'big.png', { type: 'image/png' });
    fireEvent.change(input, { target: { files: [bigFile] } });
    expect(onError).toHaveBeenCalled();
    expect(onSelect).not.toHaveBeenCalled();
  });

  it('does not call onSelect or onError when no file is in the event', async () => {
    const { ImageUploader } = await import('./ImageUploader');
    render(<ImageUploader {...defaultProps} />);
    const input = document.querySelector('input[type="file"]') as HTMLInputElement;
    fireEvent.change(input, { target: { files: [] } });
    expect(onSelect).not.toHaveBeenCalled();
    expect(onError).not.toHaveBeenCalled();
  });

  // ── Remove button ──────────────────────────────────────────────────────────

  it('calls onRemove when the remove button is clicked', async () => {
    const { ImageUploader } = await import('./ImageUploader');
    render(
      <ImageUploader
        {...defaultProps}
        file={new File(['x'], 'p.png', { type: 'image/png' })}
        preview="blob:x"
      />,
    );
    // Remove button has aria-label from i18n key compose.image_remove_aria
    const buttons = screen.getAllByRole('button');
    // There are 2 buttons: remove (icon-only) and add/change
    const removeBtn = buttons.find((b) => b.getAttribute('data-icon-only') === 'true');
    expect(removeBtn).toBeDefined();
    fireEvent.click(removeBtn!);
    expect(onRemove).toHaveBeenCalled();
  });

  it('hides the remove button when there is no preview', async () => {
    const { ImageUploader } = await import('./ImageUploader');
    render(<ImageUploader {...defaultProps} />);
    const buttons = screen.getAllByRole('button');
    // Only one button (add image), no icon-only remove button
    const iconOnly = buttons.filter((b) => b.getAttribute('data-icon-only') === 'true');
    expect(iconOnly).toHaveLength(0);
  });

  // ── File input attributes ─────────────────────────────────────────────────

  it('accepts only image MIME types on the file input', async () => {
    const { ImageUploader } = await import('./ImageUploader');
    render(<ImageUploader {...defaultProps} />);
    const input = document.querySelector('input[type="file"]') as HTMLInputElement;
    expect(input.getAttribute('accept')).toContain('image/');
  });
});
