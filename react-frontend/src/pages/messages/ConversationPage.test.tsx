// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { render, screen, waitFor } from '@/test/test-utils';
import { vi, describe, it, expect, beforeEach } from 'vitest';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: (url: string | null) => url ?? '',
  resolveAssetUrl: (url: string) => url,
}));

vi.mock('@/contexts', () => ({
  useAuth: () => ({ user: { id: 1, name: 'Alice' }, isAuthenticated: true }),
  useToast: () => ({ showToast: vi.fn() }),
  useTenant: () => ({
    tenantPath: (p: string) => `/t/test${p}`,
    hasFeature: vi.fn().mockReturnValue(true),
  }),
  usePusherOptional: () => null,

  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => key,
    i18n: { changeLanguage: vi.fn() },
  }),
}));

vi.mock('framer-motion', () => ({
  motion: new Proxy({}, {
    get: (_: object, prop: string) => {
      const { createElement, forwardRef } = require('react');
      return forwardRef(({ children, ...props }: React.PropsWithChildren<Record<string, unknown>>, ref: React.Ref<unknown>) =>
        createElement(prop as string, { ...props, ref }, children)
      );
    },
  }),
  AnimatePresence: ({ children }: React.PropsWithChildren) => children,
}));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children }: React.PropsWithChildren) => <div>{children}</div>,

  GlassButton: ({ children }: Record<string, unknown>) => children as never,
  GlassInput: () => null,
  BackToTop: () => null,
  AlgorithmLabel: () => null,
  ImagePlaceholder: () => null,
  DynamicIcon: () => null,
  ICON_MAP: {},
  ICON_NAMES: [],
  ListingSkeleton: () => null,
  MemberCardSkeleton: () => null,
  StatCardSkeleton: () => null,
  EventCardSkeleton: () => null,
  GroupCardSkeleton: () => null,
  ConversationSkeleton: () => null,
  ExchangeCardSkeleton: () => null,
  NotificationSkeleton: () => null,
  ProfileHeaderSkeleton: () => null,
  SkeletonList: () => null,
}));

vi.mock('@/components/feedback', () => ({
  LoadingScreen: () => <div data-testid="loading-screen">Loading...</div>,
}));

vi.mock('@/components/messages/MessageContextCard', () => ({
  MessageContextCard: () => <div data-testid="context-card" />,
}));

vi.mock('./components/MessageBubble', () => ({
  MessageBubble: ({ message }: { message: { content: string } }) => (
    <div data-testid="message-bubble">{message.content}</div>
  ),
}));

vi.mock('./components/MessageInputArea', () => ({
  MessageInputArea: () => <div data-testid="message-input-area" />,
}));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useParams: () => ({ id: '42' }),
    useSearchParams: () => [new URLSearchParams(), vi.fn()],
    useNavigate: () => vi.fn(),
  };
});

import { api } from '@/lib/api';
import { ConversationPage } from './ConversationPage';

const mockApi = api as unknown as {
  get: ReturnType<typeof vi.fn>;
  post: ReturnType<typeof vi.fn>;
  delete: ReturnType<typeof vi.fn>;
};

const mockConversation = {
  meta: {
    id: 42,
    other_user: { id: 20, name: 'Bob', avatar_url: null },
  },
  messages: [
    { id: 1, body: 'Hello Bob!', content: 'Hello Bob!', sender_id: 1, is_own: true, created_at: '2026-01-01T10:00:00Z', is_read: true, is_deleted: false, is_edited: false, is_voice: false, attachments: [], reactions: {} },
    { id: 2, body: 'Hi Alice!', content: 'Hi Alice!', sender_id: 20, is_own: false, created_at: '2026-01-01T10:01:00Z', is_read: true, is_deleted: false, is_edited: false, is_voice: false, attachments: [], reactions: {} },
  ],
  pagination: { older_cursor: null, newer_cursor: null, has_older: false, has_newer: false },
};

describe('ConversationPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading screen while fetching', () => {
    mockApi.get.mockReturnValue(new Promise(() => {}));
    render(<ConversationPage />);
    expect(screen.getByTestId('loading-screen')).toBeDefined();
  });

  it('renders conversation after loading', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: mockConversation });
    mockApi.post.mockResolvedValue({ success: true });

    render(<ConversationPage />);

    await waitFor(() => expect(screen.getByText('Hello Bob!')).toBeDefined());
    expect(screen.getByText('Hi Alice!')).toBeDefined();
  });

  it('renders message input area', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: mockConversation });
    mockApi.post.mockResolvedValue({ success: true });

    render(<ConversationPage />);

    await waitFor(() => expect(screen.getByTestId('message-input-area')).toBeDefined());
  });

  it('shows other user name in header', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: mockConversation });
    mockApi.post.mockResolvedValue({ success: true });

    render(<ConversationPage />);

    await waitFor(() => expect(screen.getByText('Bob')).toBeDefined());
  });

  it('renders back navigation button', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: mockConversation });

    render(<ConversationPage />);

    await waitFor(() => expect(screen.getByTestId('message-input-area')).toBeDefined());
    // Back button should be present with aria-label
    expect(screen.getByLabelText(/back/i)).toBeDefined();
  });
});
