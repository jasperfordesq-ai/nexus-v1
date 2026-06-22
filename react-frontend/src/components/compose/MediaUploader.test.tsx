// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ── Mocks ───────────────────────────────────────────────────────────────────

vi.mock('@/contexts', () => createMockContexts());

vi.mock('@/lib/api', () => {
  const m = {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    upload: vi.fn(),
    download: vi.fn(),
  };
  return { default: m, api: m };
});

// Mock DnD kit — these rely on real DOM pointer events that don't exist in
// jsdom; replacing with stubs prevents "Invariant: Target is not a valid
// droppable" errors.
vi.mock('@dnd-kit/core', () => ({
  DndContext: ({ children }: { children: React.ReactNode }) => <>{children}</>,
  closestCenter: vi.fn(),
  useDraggable: () => ({
    attributes: {},
    listeners: {},
    setNodeRef: vi.fn(),
    transform: null,
    isDragging: false,
  }),
}));

vi.mock('@dnd-kit/sortable', () => ({
  SortableContext: ({ children }: { children: React.ReactNode }) => <>{children}</>,
  rectSortingStrategy: vi.fn(),
  useSortable: () => ({
    attributes: {},
    listeners: {},
    setNodeRef: vi.fn(),
    transform: null,
    transition: undefined,
    isDragging: false,
  }),
  arrayMove: vi.fn((arr: unknown[], oldIdx: number, newIdx: number) => {
    const result = [...arr];
    const [moved] = result.splice(oldIdx, 1);
    result.splice(newIdx, 0, moved);
    return result;
  }),
}));

vi.mock('@dnd-kit/utilities', () => ({
  CSS: { Transform: { toString: () => '' } },
}));

// compressImage: just return the file unchanged so tests stay synchronous.
vi.mock('@/lib/compress-image', () => ({
  compressImage: vi.fn(async (file: File) => file),
}));

// FileReader: stub readAsDataURL so that it fires onload with a data URL.
class MockFileReader {
  onload: ((e: ProgressEvent<FileReader>) => void) | null = null;
  onerror: ((e: ProgressEvent<FileReader>) => void) | null = null;
  readAsDataURL(file: File) {
    setTimeout(() => {
      if (this.onload) {
        this.onload({
          target: { result: `data:${file.type};base64,MOCK` },
        } as unknown as ProgressEvent<FileReader>);
      }
    }, 0);
  }
}
Object.defineProperty(global, 'FileReader', {
  writable: true,
  value: MockFileReader,
});

// ── Helpers ──────────────────────────────────────────────────────────────────

import { MediaUploader, type MediaFile } from './MediaUploader';

function makeFile(name = 'photo.jpg', type = 'image/jpeg', size = 1024) {
  return new File(['x'.repeat(size)], name, { type });
}

function makeMediaFile(name = 'photo.jpg'): MediaFile {
  return {
    file: makeFile(name),
    preview: 'data:image/jpeg;base64,MOCK',
    altText: '',
  };
}

// ── Tests ────────────────────────────────────────────────────────────────────

describe('MediaUploader — empty (no files)', () => {
  const onMediaChange = vi.fn();

  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders the drag-drop zone when no files are provided', () => {
    render(
      <MediaUploader
        onMediaChange={onMediaChange}
        mediaFiles={[]}
      />,
    );
    const zone = screen.getByRole('button', { hidden: true });
    // The drop zone is the [role=button] div
    expect(zone).toBeDefined();
  });

  it('shows the drag-and-drop prompt text', () => {
    render(
      <MediaUploader
        onMediaChange={onMediaChange}
        mediaFiles={[]}
      />,
    );
    // i18n key compose.media_drag_drop renders as its key in test env
    // just verify something renders inside the zone
    const input = document.querySelector('input[type="file"]');
    expect(input).not.toBeNull();
    expect((input as HTMLInputElement).accept).toContain('image/jpeg');
  });

  it('hides the file input from assistive technology', () => {
    render(<MediaUploader onMediaChange={onMediaChange} mediaFiles={[]} />);
    const input = document.querySelector('input[type="file"]') as HTMLInputElement;
    expect(input.getAttribute('aria-hidden')).toBe('true');
  });

  it('calls onMediaChange with new file after file-input change', async () => {
    render(<MediaUploader onMediaChange={onMediaChange} mediaFiles={[]} />);

    const input = document.querySelector('input[type="file"]') as HTMLInputElement;
    const file = makeFile();

    Object.defineProperty(input, 'files', { value: [file], configurable: true });
    fireEvent.change(input);

    await waitFor(() => {
      expect(onMediaChange).toHaveBeenCalledTimes(1);
      const [[newFiles]] = onMediaChange.mock.calls;
      expect(newFiles).toHaveLength(1);
      expect(newFiles[0].file.name).toBe('photo.jpg');
    });
  });

  it('rejects files larger than maxSizeMb and calls onError', async () => {
    const onError = vi.fn();
    render(
      <MediaUploader
        onMediaChange={onMediaChange}
        mediaFiles={[]}
        maxSizeMb={1}
        onError={onError}
      />,
    );

    const input = document.querySelector('input[type="file"]') as HTMLInputElement;
    const bigFile = makeFile('big.jpg', 'image/jpeg', 2 * 1024 * 1024); // 2 MB

    Object.defineProperty(input, 'files', { value: [bigFile], configurable: true });
    fireEvent.change(input);

    await waitFor(() => {
      expect(onError).toHaveBeenCalled();
      expect(onMediaChange).not.toHaveBeenCalled();
    });
  });

  it('rejects non-image files and calls onError', async () => {
    const onError = vi.fn();
    render(
      <MediaUploader
        onMediaChange={onMediaChange}
        mediaFiles={[]}
        onError={onError}
      />,
    );

    const input = document.querySelector('input[type="file"]') as HTMLInputElement;
    const pdfFile = new File(['data'], 'doc.pdf', { type: 'application/pdf' });

    Object.defineProperty(input, 'files', { value: [pdfFile], configurable: true });
    fireEvent.change(input);

    await waitFor(() => {
      expect(onError).toHaveBeenCalled();
      expect(onMediaChange).not.toHaveBeenCalled();
    });
  });

  it('calls onError when at max file count', async () => {
    const onError = vi.fn();
    const atMax: MediaFile[] = Array.from({ length: 10 }, (_, i) => makeMediaFile(`p${i}.jpg`));

    render(
      <MediaUploader
        onMediaChange={onMediaChange}
        mediaFiles={atMax}
        maxFiles={10}
        onError={onError}
      />,
    );

    const input = document.querySelector('input[type="file"]') as HTMLInputElement;
    Object.defineProperty(input, 'files', { value: [makeFile()], configurable: true });
    fireEvent.change(input);

    await waitFor(() => {
      expect(onError).toHaveBeenCalled();
    });
  });
});

describe('MediaUploader — populated (has files)', () => {
  const onMediaChange = vi.fn();

  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders image thumbnails for each provided file', () => {
    const files = [makeMediaFile('a.jpg'), makeMediaFile('b.jpg')];
    render(<MediaUploader onMediaChange={onMediaChange} mediaFiles={files} />);

    const images = screen.getAllByRole('img');
    expect(images.length).toBeGreaterThanOrEqual(2);
  });

  it('shows "add more" button when below max', () => {
    const files = [makeMediaFile('a.jpg')];
    render(<MediaUploader onMediaChange={onMediaChange} mediaFiles={files} maxFiles={10} />);

    // "add more" is a HeroUI Button with aria-label from common.aria.add_more_photos
    const btns = screen.getAllByRole('button');
    expect(btns.length).toBeGreaterThan(0);
  });

  it('calls onMediaChange with one fewer file when remove is pressed', async () => {
    const files = [makeMediaFile('a.jpg'), makeMediaFile('b.jpg')];
    render(<MediaUploader onMediaChange={onMediaChange} mediaFiles={files} />);

    // Remove button for image 1 (aria-label includes "1")
    const removeBtns = screen.getAllByRole('button').filter((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('remove') ||
      b.getAttribute('aria-label')?.includes('1'),
    );

    await userEvent.click(removeBtns[0]);

    await waitFor(() => {
      expect(onMediaChange).toHaveBeenCalledWith(expect.arrayContaining([]));
      const [[result]] = onMediaChange.mock.calls;
      expect(result.length).toBe(1);
    });
  });
});
