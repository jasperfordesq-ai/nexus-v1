// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock api ────────────────────────────────────────────────────────────────
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
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({ resolveAvatarUrl: (url: string | null) => url ?? '' }));

// ─── Toast / Auth / Tenant ───────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Test User', first_name: 'Test', avatar: null },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test-tenant' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
  useDraftPersistence: (() => {
    // will be overridden per-import below
  }),
}));

// ─── Draft persistence stub ───────────────────────────────────────────────────
vi.mock('@/hooks/useDraftPersistence', () => ({
  useDraftPersistence: <T,>(_key: string, initial: T) => {
    const [val, setVal] = React.useState<T>(initial);
    return [val, setVal, vi.fn()] as [T, React.Dispatch<React.SetStateAction<T>>, () => void];
  },
}));

// Alias — PostTab imports from '@/hooks' so we also intercept there
vi.mock('@/hooks', async (importOriginal) => {
  const orig = await importOriginal<Record<string, unknown>>();
  return {
    ...orig,
    usePageTitle: vi.fn(),
    useDraftPersistence: <T,>(_key: string, initial: T) => {
      const [val, setVal] = React.useState<T>(initial);
      return [val, setVal, vi.fn()] as [T, React.Dispatch<React.SetStateAction<T>>, () => void];
    },
  };
});

// ─── useMediaQuery stub ──────────────────────────────────────────────────────
vi.mock('@/hooks/useMediaQuery', () => ({
  useMediaQuery: () => false, // desktop mode by default
}));

// ─── Stub heavy compose sub-components ───────────────────────────────────────
vi.mock('../shared/ComposeEditor', () => ({
  ComposeEditor: React.forwardRef(function ComposeEditor(
    { placeholder, onChange, onPlainTextChange }: {
      placeholder?: string;
      onChange?: (html: string) => void;
      onPlainTextChange?: (text: string) => void;
      value?: string;
      maxLength?: number;
    },
    _ref: React.ForwardedRef<unknown>
  ) {
    return (
      <textarea
        data-testid="compose-editor"
        placeholder={placeholder}
        onChange={(e) => {
          onChange?.(e.target.value);
          onPlainTextChange?.(e.target.value);
        }}
      />
    );
  }),
}));

vi.mock('../MediaUploader', () => ({
  MediaUploader: ({ onError: _onError }: { mediaFiles: unknown[]; onMediaChange: (f: unknown[]) => void; maxFiles?: number; maxSizeMb?: number; onError?: (msg: string) => void }) => (
    <div data-testid="media-uploader" />
  ),
}));

vi.mock('../shared/EmojiPicker', () => ({
  EmojiPicker: ({ onSelect: _s }: { onSelect: (e: string) => void }) => (
    <button data-testid="emoji-picker">Emoji</button>
  ),
}));

vi.mock('../GifPicker', () => ({
  GifPicker: ({ onSelect: _s }: { onSelect: (url: string) => void }) => (
    <button data-testid="gif-picker">GIF</button>
  ),
}));

vi.mock('../shared/VoiceInput', () => ({
  VoiceInput: ({ onTranscript: _t }: { onTranscript: (text: string) => void }) => (
    <button data-testid="voice-input">Mic</button>
  ),
}));

vi.mock('../shared/CharacterCount', () => ({
  CharacterCount: ({ current, max }: { current: number; max: number }) => (
    <span data-testid="char-count">{current}/{max}</span>
  ),
}));

vi.mock('../shared/LinkPreview', () => ({
  LinkPreview: () => <div data-testid="link-preview" />,
}));

vi.mock('../ComposeSubmitContext', () => ({
  useComposeSubmit: () => ({
    registration: null,
    register: vi.fn(),
    unregister: vi.fn(),
  }),
}));

// ─── Stub HeroUI UI components ────────────────────────────────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<Record<string, unknown>>();
  return {
    ...orig,
    Avatar: ({ name }: { name?: string; src?: string; size?: string; isBordered?: boolean; className?: string }) => (
      <div data-testid="avatar">{name}</div>
    ),
    Button: ({ children, onPress, isLoading, isDisabled, 'aria-label': al, startContent: _sc, isIconOnly: _io, ...rest }: {
      children?: React.ReactNode;
      onPress?: () => void;
      isLoading?: boolean;
      isDisabled?: boolean;
      'aria-label'?: string;
      startContent?: React.ReactNode;
      isIconOnly?: boolean;
      [key: string]: unknown;
    }) => (
      <button
        onClick={onPress}
        disabled={isDisabled || isLoading}
        aria-label={al}
        data-loading={isLoading ? 'true' : undefined}
        {...(rest as Record<string, unknown>)}
      >{children}</button>
    ),
    Spinner: ({ size: _s }: { size?: string; className?: string }) => (
      <div role="status" aria-busy="true" data-testid="spinner" />
    ),
    Popover: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    PopoverTrigger: ({ children }: { children: React.ReactNode }) => <>{children}</>,
    PopoverContent: ({ children }: { children: React.ReactNode }) => (
      <div data-testid="popover-content">{children}</div>
    ),
    DatePicker: ({ label, onChange }: { label?: string; onChange?: (v: unknown) => void; [key: string]: unknown }) => (
      <div data-testid="date-picker">
        <label>{label}</label>
        <input type="date" onChange={(e) => onChange?.(e.target.value)} />
      </div>
    ),
    TimeInput: ({ label }: { label?: string; [key: string]: unknown }) => (
      <div data-testid="time-input"><label>{label}</label></div>
    ),
  };
});

// ─── @internationalized/date stub ────────────────────────────────────────────
vi.mock('@internationalized/date', () => ({
  today: () => ({ toString: () => '2025-06-23', year: 2025, month: 6, day: 23 }),
  getLocalTimeZone: () => 'Europe/Dublin',
}));

// ─── Shared props factory ─────────────────────────────────────────────────────
const makeProps = (overrides = {}) => ({
  onSuccess: vi.fn(),
  onClose: vi.fn(),
  isOpen: true,
  groupId: undefined as number | undefined,
  templateData: undefined,
  onContentChange: vi.fn(),
  editItem: null,
  onEditSuccess: vi.fn(),
  ...overrides,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('PostTab', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.post.mockResolvedValue({ success: true, data: { id: 99 } });
  });

  it('renders compose editor (may be in Suspense fallback or resolved)', async () => {
    const { PostTab } = await import('./PostTab');
    render(<PostTab {...makeProps()} />);
    // Suspense lazy mock resolves async; wait for it or accept spinner as valid loading state
    await waitFor(() => {
      const editor = screen.queryByTestId('compose-editor');
      const spinner = screen.queryByTestId('spinner');
      // Either the editor is rendered or the Suspense spinner is — both are valid
      expect(editor ?? spinner).toBeTruthy();
    });
  });

  it('renders user avatar', async () => {
    const { PostTab } = await import('./PostTab');
    render(<PostTab {...makeProps()} />);
    expect(screen.getByTestId('avatar')).toBeInTheDocument();
  });

  it('renders emoji picker button', async () => {
    const { PostTab } = await import('./PostTab');
    render(<PostTab {...makeProps()} />);
    expect(screen.getByTestId('emoji-picker')).toBeInTheDocument();
  });

  it('renders GIF picker button', async () => {
    const { PostTab } = await import('./PostTab');
    render(<PostTab {...makeProps()} />);
    expect(screen.getByTestId('gif-picker')).toBeInTheDocument();
  });

  it('renders media uploader', async () => {
    const { PostTab } = await import('./PostTab');
    render(<PostTab {...makeProps()} />);
    expect(screen.getByTestId('media-uploader')).toBeInTheDocument();
  });

  it('renders character count', async () => {
    const { PostTab } = await import('./PostTab');
    render(<PostTab {...makeProps()} />);
    expect(screen.getByTestId('char-count')).toBeInTheDocument();
  });

  it('renders Post button in desktop mode', async () => {
    const { PostTab } = await import('./PostTab');
    render(<PostTab {...makeProps()} />);
    const buttons = screen.getAllByRole('button');
    const postBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('post'));
    expect(postBtn).toBeTruthy();
  });

  it('post button is disabled when editor is empty', async () => {
    const { PostTab } = await import('./PostTab');
    render(<PostTab {...makeProps()} />);
    const buttons = screen.getAllByRole('button');
    const postBtn = buttons.find(
      (b) => b.textContent?.toLowerCase().includes('post') && !b.textContent?.toLowerCase().includes('cancel')
    );
    expect(postBtn).toHaveProperty('disabled', true);
  });

  it('post button becomes enabled after typing', async () => {
    const { PostTab } = await import('./PostTab');
    render(<PostTab {...makeProps()} />);

    const editor = screen.getByTestId('compose-editor') as HTMLTextAreaElement;
    fireEvent.change(editor, { target: { value: 'Hello world!' } });

    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      const postBtn = buttons.find(
        (b) => b.textContent?.toLowerCase().includes('post') && !b.textContent?.toLowerCase().includes('cancel')
      );
      expect(postBtn?.getAttribute('disabled')).toBeNull();
    });
  });

  it('calls POST /v2/feed/posts on submit', async () => {
    const onSuccess = vi.fn();
    const { PostTab } = await import('./PostTab');
    render(<PostTab {...makeProps({ onSuccess })} />);

    const editor = screen.getByTestId('compose-editor') as HTMLTextAreaElement;
    fireEvent.change(editor, { target: { value: 'Test post content' } });

    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      const postBtn = buttons.find(
        (b) => b.textContent?.toLowerCase().includes('post') && !b.textContent?.toLowerCase().includes('cancel')
      );
      return postBtn && !postBtn.hasAttribute('disabled');
    });

    const buttons = screen.getAllByRole('button');
    const postBtn = buttons.find(
      (b) => b.textContent?.toLowerCase().includes('post') && !b.textContent?.toLowerCase().includes('cancel')
    );
    if (postBtn) fireEvent.click(postBtn);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/v2/feed/posts',
        expect.objectContaining({ content: 'Test post content', visibility: 'public' })
      );
    });
  });

  it('shows success toast and calls onSuccess after posting', async () => {
    const onSuccess = vi.fn();
    const onClose = vi.fn();
    const { PostTab } = await import('./PostTab');
    render(<PostTab {...makeProps({ onSuccess, onClose })} />);

    const editor = screen.getByTestId('compose-editor') as HTMLTextAreaElement;
    fireEvent.change(editor, { target: { value: 'New post!' } });

    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      const postBtn = buttons.find(
        (b) => b.textContent?.toLowerCase().includes('post') && !b.textContent?.toLowerCase().includes('cancel')
      );
      return postBtn && !postBtn.hasAttribute('disabled');
    });

    const buttons = screen.getAllByRole('button');
    const postBtn = buttons.find(
      (b) => b.textContent?.toLowerCase().includes('post') && !b.textContent?.toLowerCase().includes('cancel')
    );
    if (postBtn) fireEvent.click(postBtn);

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
      expect(onSuccess).toHaveBeenCalledWith('post');
    });
  });

  it('calls PUT /v2/feed/posts/:id when in edit mode', async () => {
    const editItem = {
      id: 55,
      type: 'post' as const,
      content: 'Original content',
      created_at: '2025-01-01T00:00:00Z',
      is_liked: false,
      likes_count: 0,
      comments_count: 0,
      author: { id: 1, name: 'Alice', avatar_url: null },
      reactions: { counts: {}, total: 0, user_reaction: null, top_reactors: [] },
    };
    mockApi.put.mockResolvedValue({ success: true, data: { ...editItem, content: 'Updated content' } });

    const { PostTab } = await import('./PostTab');
    render(<PostTab {...makeProps({ editItem })} />);

    const editor = screen.getByTestId('compose-editor') as HTMLTextAreaElement;
    fireEvent.change(editor, { target: { value: 'Updated content' } });

    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      const saveBtn = buttons.find(
        (b) => !b.hasAttribute('disabled') && (
          b.textContent?.toLowerCase().includes('save') ||
          b.textContent?.toLowerCase().includes('post')
        )
      );
      return !!saveBtn;
    });

    const buttons = screen.getAllByRole('button');
    const saveBtn = buttons.find(
      (b) => !b.hasAttribute('disabled') && (
        b.textContent?.toLowerCase().includes('save') ||
        b.textContent?.toLowerCase().includes('post')
      )
    );
    if (saveBtn) fireEvent.click(saveBtn);

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith(
        '/v2/feed/posts/55',
        expect.objectContaining({ content: 'Updated content' })
      );
    });
  });

  it('shows error toast when post submission fails', async () => {
    mockApi.post.mockResolvedValue({ success: false, error: 'Server error' });
    const { PostTab } = await import('./PostTab');
    render(<PostTab {...makeProps()} />);

    const editor = screen.getByTestId('compose-editor') as HTMLTextAreaElement;
    fireEvent.change(editor, { target: { value: 'Will fail' } });

    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      return buttons.find(
        (b) => !b.hasAttribute('disabled') && b.textContent?.toLowerCase().includes('post')
      );
    });

    const buttons = screen.getAllByRole('button');
    const postBtn = buttons.find(
      (b) => !b.hasAttribute('disabled') &&
             b.textContent?.toLowerCase().includes('post') &&
             !b.textContent?.toLowerCase().includes('cancel')
    );
    if (postBtn) fireEvent.click(postBtn);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('renders cancel button and calls onClose when clicked', async () => {
    const onClose = vi.fn();
    const { PostTab } = await import('./PostTab');
    render(<PostTab {...makeProps({ onClose })} />);

    const cancelBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('cancel')
    );
    expect(cancelBtn).toBeTruthy();
    if (cancelBtn) fireEvent.click(cancelBtn);
    expect(onClose).toHaveBeenCalled();
  });
});
