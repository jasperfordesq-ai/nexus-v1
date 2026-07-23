// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

// Stub helpers used by QuotedPostEmbed (child component).
// Must also export cn since HeroUI/UI components import it from @/lib/helpers.
vi.mock(import('@/lib/helpers'), async (importOriginal) => ({
  ...(await importOriginal()),
  resolveAvatarUrl: (url: string | null | undefined) => url ?? '',
  resolveAssetUrl: (url: string | null | undefined) => url ?? '',
  formatRelativeTime: () => '10m ago',
  cn: (...classes: (string | undefined | null | false)[]) => classes.filter(Boolean).join(' '),
}));

import { api } from '@/lib/api';
import { QuotePostModal } from './QuotePostModal';
import type { FeedItem } from './types';

const MOCK_POST: FeedItem = {
  id: 55,
  content: 'Original post content here',
  created_at: '2026-01-01T00:00:00Z',
  type: 'post',
  likes_count: 0,
  comments_count: 0,
  is_liked: false,
  author_id: 10,
  author_name: 'Original Author',
  author_avatar: null,
};

const baseProps = {
  isOpen: true,
  onClose: vi.fn(),
  post: MOCK_POST,
  onSuccess: vi.fn(),
};

function renderModal(overrides: Partial<typeof baseProps> = {}) {
  const props = { ...baseProps, ...overrides };
  return render(<QuotePostModal {...props} />);
}

describe('QuotePostModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    baseProps.onClose = vi.fn();
    baseProps.onSuccess = vi.fn();
  });

  it('renders the modal when isOpen=true', () => {
    renderModal();
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  it('renders the quoted post author name in the embedded preview', () => {
    renderModal();
    expect(screen.getByText('Original Author')).toBeInTheDocument();
  });

  it('renders the original post content in the embedded preview', () => {
    renderModal();
    expect(screen.getByText('Original post content here')).toBeInTheDocument();
  });

  it('renders a textarea for user commentary', () => {
    renderModal();
    expect(screen.getByRole('textbox')).toBeInTheDocument();
  });

  it('renders Cancel and Post buttons', () => {
    renderModal();
    expect(screen.getByRole('button', { name: /cancel/i })).toBeInTheDocument();
    // quote_submit i18n key → "Post"
    expect(screen.getByRole('button', { name: /^post$/i })).toBeInTheDocument();
  });

  it('Submit button is disabled when textarea is empty', () => {
    renderModal();
    // quote_submit i18n key → "Post". HeroUI isDisabled sets native disabled.
    const submitBtn = screen.getByRole('button', { name: /^post$/i });
    expect(submitBtn).toBeDisabled();
  });

  it('Submit button becomes enabled after typing commentary', async () => {
    renderModal();
    const textarea = screen.getByRole('textbox');
    fireEvent.change(textarea, { target: { value: 'My commentary here' } });
    await waitFor(() => {
      const submitBtn = screen.getByRole('button', { name: /^post$/i });
      expect(submitBtn).not.toBeDisabled();
    });
  });

  it('does not call api.post when content is empty (Submit is disabled)', () => {
    renderModal();
    // With no content the "Post" button is native-disabled — clicking is a no-op.
    // Verify the API is never called from just mounting the modal.
    expect(api.post).not.toHaveBeenCalled();
  });

  it('posts the correct payload on successful submit', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: true });
    renderModal();

    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Great quote!' } });
    fireEvent.click(screen.getByRole('button', { name: /^post$/i }));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/v2/feed/posts', {
        content: 'Great quote!',
        quoted_post_id: 55,
      });
    });
  });

  it('trims whitespace from content before posting', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: true });
    renderModal();

    fireEvent.change(screen.getByRole('textbox'), { target: { value: '  Trimmed!  ' } });
    fireEvent.click(screen.getByRole('button', { name: /^post$/i }));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        '/v2/feed/posts',
        expect.objectContaining({ content: 'Trimmed!' })
      );
    });
  });

  it('shows success toast, calls onSuccess, and closes on successful submit', async () => {
    const onClose = vi.fn();
    const onSuccess = vi.fn();
    vi.mocked(api.post).mockResolvedValue({ success: true });

    renderModal({ onClose, onSuccess });

    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Quoting this!' } });
    fireEvent.click(screen.getByRole('button', { name: /^post$/i }));

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
      expect(onSuccess).toHaveBeenCalled();
      expect(onClose).toHaveBeenCalled();
    });
  });

  it('shows error toast when API returns success=false', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: false, error: 'Post failed' });
    renderModal();

    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Something here' } });
    fireEvent.click(screen.getByRole('button', { name: /^post$/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows error toast when API throws', async () => {
    vi.mocked(api.post).mockRejectedValue(new Error('Network error'));
    renderModal();

    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Something here' } });
    fireEvent.click(screen.getByRole('button', { name: /^post$/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('calls onClose when Cancel is pressed', async () => {
    const onClose = vi.fn();
    renderModal({ onClose });

    fireEvent.click(screen.getByRole('button', { name: /cancel/i }));

    await waitFor(() => {
      expect(onClose).toHaveBeenCalled();
    });
  });

  it('works with a post that uses nested author object instead of flat fields', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: true });
    const nestedAuthorPost: FeedItem = {
      ...MOCK_POST,
      author_id: undefined,
      author_name: undefined,
      author_avatar: undefined,
      author: { id: 20, name: 'Nested Author', avatar_url: 'https://example.com/avatar.jpg' },
    };

    renderModal({ post: nestedAuthorPost });

    expect(screen.getByText('Nested Author')).toBeInTheDocument();
  });
});
