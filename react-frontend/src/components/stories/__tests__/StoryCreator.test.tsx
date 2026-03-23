// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for StoryCreator component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@/test/test-utils';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    upload: vi.fn(),
  },
}));

const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
};

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
  useAuth: () => ({ user: { id: 1, name: 'Test User' }, isAuthenticated: true, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test', tagline: null }, branding: { name: 'Test', logo_url: null }, tenantSlug: 'test', tenantPath: (p: string) => '/test' + p, isLoading: false, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/lib/compress-image', () => ({
  compressImage: vi.fn(async (file: File) => file),
}));

vi.mock('framer-motion', async () => {
  const actual = await vi.importActual<typeof import('framer-motion')>('framer-motion');
  return {
    ...actual,
    motion: {
      ...actual.motion,
      div: ({ children, ...props }: React.PropsWithChildren<Record<string, unknown>>) => {
        const htmlProps: Record<string, unknown> = {};
        for (const [k, v] of Object.entries(props)) {
          if (!['initial', 'animate', 'exit', 'transition', 'variants', 'whileHover', 'whileTap', 'layout'].includes(k)) {
            htmlProps[k] = v;
          }
        }
        return <div {...htmlProps}>{children}</div>;
      },
    },
    AnimatePresence: ({ children }: React.PropsWithChildren) => <>{children}</>,
  };
});

import { StoryCreator } from '../StoryCreator';

describe('StoryCreator', () => {
  const defaultProps = {
    onClose: vi.fn(),
    onCreated: vi.fn(),
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    render(<StoryCreator {...defaultProps} />);
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  it('has an accessible dialog label', () => {
    render(<StoryCreator {...defaultProps} />);
    expect(screen.getByRole('dialog')).toHaveAttribute('aria-label', 'Create story');
  });

  it('renders the "Create Story" heading', () => {
    render(<StoryCreator {...defaultProps} />);
    expect(screen.getByText('Create Story')).toBeInTheDocument();
  });

  it('renders three mode tabs: Photo, Text, Poll', () => {
    render(<StoryCreator {...defaultProps} />);
    expect(screen.getByRole('button', { name: 'Photo mode' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Text mode' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Poll mode' })).toBeInTheDocument();
  });

  it('defaults to text mode', () => {
    render(<StoryCreator {...defaultProps} />);
    const textTab = screen.getByRole('button', { name: 'Text mode' });
    expect(textTab).toHaveAttribute('aria-pressed', 'true');
  });

  it('renders the Share Story button', () => {
    render(<StoryCreator {...defaultProps} />);
    expect(screen.getByText('Share Story')).toBeInTheDocument();
  });

  it('renders the Discard button', () => {
    render(<StoryCreator {...defaultProps} />);
    expect(screen.getByText('Discard')).toBeInTheDocument();
  });

  it('renders the close button', () => {
    render(<StoryCreator {...defaultProps} />);
    expect(screen.getByRole('button', { name: 'Close creator' })).toBeInTheDocument();
  });

  it('calls onClose when close button is clicked', () => {
    render(<StoryCreator {...defaultProps} />);
    fireEvent.click(screen.getByRole('button', { name: 'Close creator' }));
    expect(defaultProps.onClose).toHaveBeenCalled();
  });

  it('calls onClose when Discard is clicked in text mode with no content', () => {
    render(<StoryCreator {...defaultProps} />);
    fireEvent.click(screen.getByText('Discard'));
    expect(defaultProps.onClose).toHaveBeenCalled();
  });

  it('switches to photo mode when Photo tab is clicked', () => {
    render(<StoryCreator {...defaultProps} />);
    fireEvent.click(screen.getByRole('button', { name: 'Photo mode' }));
    expect(screen.getByRole('button', { name: 'Photo mode' })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByText('Add Photo')).toBeInTheDocument();
  });

  it('switches to poll mode when Poll tab is clicked', () => {
    render(<StoryCreator {...defaultProps} />);
    fireEvent.click(screen.getByRole('button', { name: 'Poll mode' }));
    expect(screen.getByRole('button', { name: 'Poll mode' })).toHaveAttribute('aria-pressed', 'true');
  });

  it('renders "Start typing..." placeholder in text mode', () => {
    render(<StoryCreator {...defaultProps} />);
    expect(screen.getByText('Start typing...')).toBeInTheDocument();
  });

  it('renders gradient background picker buttons in text mode', () => {
    render(<StoryCreator {...defaultProps} />);
    expect(screen.getByText('Background')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Purple Blue' })).toBeInTheDocument();
  });

  it('renders font picker buttons in text mode', () => {
    render(<StoryCreator {...defaultProps} />);
    expect(screen.getByText('Font')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Sans font' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Serif font' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Mono font' })).toBeInTheDocument();
  });

  it('shows error toast when submitting text mode with empty text', async () => {
    render(<StoryCreator {...defaultProps} />);
    fireEvent.click(screen.getByText('Share Story'));
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalledWith('Please enter some text');
    });
  });

  it('shows error toast when submitting photo mode with no image', async () => {
    render(<StoryCreator {...defaultProps} />);
    fireEvent.click(screen.getByRole('button', { name: 'Photo mode' }));
    fireEvent.click(screen.getByText('Share Story'));
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalledWith('Please select an image');
    });
  });

  it('renders poll mode UI with question input and two option inputs', () => {
    render(<StoryCreator {...defaultProps} />);
    fireEvent.click(screen.getByRole('button', { name: 'Poll mode' }));
    expect(screen.getByText('Options')).toBeInTheDocument();
    expect(screen.getByText('Add Option')).toBeInTheDocument();
  });

  it('shows "Your question..." placeholder in poll mode', () => {
    render(<StoryCreator {...defaultProps} />);
    fireEvent.click(screen.getByRole('button', { name: 'Poll mode' }));
    expect(screen.getByText('Your question...')).toBeInTheDocument();
  });

  it('shows error toast when submitting poll with empty question', async () => {
    render(<StoryCreator {...defaultProps} />);
    fireEvent.click(screen.getByRole('button', { name: 'Poll mode' }));
    fireEvent.click(screen.getByText('Share Story'));
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalledWith('Please enter a poll question');
    });
  });

  it('renders photo mode upload area with Select image button', () => {
    render(<StoryCreator {...defaultProps} />);
    fireEvent.click(screen.getByRole('button', { name: 'Photo mode' }));
    expect(screen.getByRole('button', { name: 'Select image for story' })).toBeInTheDocument();
  });

  it('renders hidden file input in photo mode', () => {
    render(<StoryCreator {...defaultProps} />);
    fireEvent.click(screen.getByRole('button', { name: 'Photo mode' }));
    const fileInput = screen.getByLabelText('Choose image file');
    expect(fileInput).toBeInTheDocument();
    expect(fileInput).toHaveAttribute('type', 'file');
    expect(fileInput).toHaveAttribute('accept', 'image/*');
  });

  it('submits text story successfully via api.post', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ success: true });
    render(<StoryCreator {...defaultProps} />);

    // Type text into the textarea — find by placeholder
    const textarea = screen.getByPlaceholderText("What's on your mind?");
    fireEvent.change(textarea, { target: { value: 'Hello world story' } });

    fireEvent.click(screen.getByText('Share Story'));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/v2/stories', expect.objectContaining({
        media_type: 'text',
        text_content: 'Hello world story',
      }));
      expect(mockToast.success).toHaveBeenCalledWith('Story shared!');
      expect(defaultProps.onCreated).toHaveBeenCalled();
    });
  });

  it('shows error toast on text story API failure', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ success: false, error: 'Server error' });
    render(<StoryCreator {...defaultProps} />);

    const textarea = screen.getByPlaceholderText("What's on your mind?");
    fireEvent.change(textarea, { target: { value: 'Hello story' } });

    fireEvent.click(screen.getByText('Share Story'));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalledWith('Server error');
    });
  });
});
