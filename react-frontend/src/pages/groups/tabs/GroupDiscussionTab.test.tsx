// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';
import React from 'react';

// ─── No @/lib/api import in GroupDiscussionTab — it receives data via props ───
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/lib/helpers', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/helpers')>();
  return {
    ...actual,
    resolveAvatarUrl: (url: string | null) => url ?? '',
    formatRelativeTime: () => 'just now',
  };
});

// ─── Contexts ────────────────────────────────────────────────────────────────
const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
};
const mockSocialPanel = vi.hoisted(() => vi.fn());

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Member User' },
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
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Stub heavy child components ─────────────────────────────────────────────
vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description, action }: {
    title?: string;
    description?: string;
    action?: React.ReactNode;
  }) => (
    <div data-testid="empty-state">
      {title && <p data-testid="empty-title">{title}</p>}
      {description && <p data-testid="empty-desc">{description}</p>}
      {action}
    </div>
  ),
}));

vi.mock('@/components/social', () => ({
  SocialInteractionPanel: (props: Record<string, unknown>) => {
    mockSocialPanel(props);
    return <div data-testid="social-panel" />;
  },
}));

vi.mock('@/components/social/SocialInteractionPanel', () => ({
  SocialInteractionPanel: (props: Record<string, unknown>) => {
    mockSocialPanel(props);
    return <div data-testid="social-panel" />;
  },
}));

vi.mock('@/components/ui/SafeHtml', () => ({
  SafeHtml: ({ content }: { content?: string }) => <div data-testid="safe-html">{content}</div>,
}));

// Stub motion so AnimatePresence/motion.div render immediately in jsdom
vi.mock('@/lib/motion', () => ({
  motion: {
    div: ({ children, layout: _layout, ...rest }: React.HTMLAttributes<HTMLDivElement> & { layout?: boolean }) => (
      <div {...rest}>{children}</div>
    ),
  },
  AnimatePresence: ({ children }: { children?: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    GlassCard: ({ children, className }: { children?: React.ReactNode; className?: string }) => (
      <div data-testid="glass-card" className={className}>{children}</div>
    ),
    Avatar: ({ name }: { name?: string }) => <div data-testid="avatar" aria-label={name}>{name?.[0]}</div>,
    Spinner: () => <div role="status" aria-busy="true" />,
    Textarea: ({ placeholder, value, onChange, 'aria-label': ariaLabel }: {
      placeholder?: string;
      value?: string;
      onChange?: React.ChangeEventHandler<HTMLTextAreaElement>;
      'aria-label'?: string;
    }) => (
      <textarea
        placeholder={placeholder}
        value={value}
        onChange={onChange}
        aria-label={ariaLabel}
      />
    ),
    Button: ({ children, onPress, onClick, 'aria-label': ariaLabel, isLoading, isDisabled, startContent }: {
      children?: React.ReactNode;
      onPress?: () => void;
      onClick?: () => void;
      'aria-label'?: string;
      isLoading?: boolean;
      isDisabled?: boolean;
      startContent?: React.ReactNode;
      [key: string]: unknown;
    }) => (
      <button
        aria-label={ariaLabel}
        onClick={onPress ?? onClick}
        disabled={isDisabled}
      >
        {isLoading ? 'Loading…' : children}
      </button>
    ),
  };
});

// ─── Fixtures ────────────────────────────────────────────────────────────────
const makeDiscussion = (overrides = {}): import('./GroupDiscussionTab').Discussion => ({
  id: 1,
  title: 'Weekly check-in',
  content: 'How is everyone doing?',
  author: { id: 10, name: 'Alice', avatar_url: null },
  reply_count: 3,
  comments_count: 3,
  likes_count: 5,
  is_liked: false,
  is_pinned: false,
  last_reply_at: '2025-06-01T12:00:00Z',
  created_at: '2025-06-01T10:00:00Z',
  ...overrides,
});

const makeExpandedDiscussion = (
  base: import('./GroupDiscussionTab').Discussion
): import('./GroupDiscussionTab').DiscussionDetail => ({
  ...base,
  messagesHasMore: false,
  messages: [
    {
      id: 100,
      content: 'Doing great!',
      author: { id: 20, name: 'Bob', avatar_url: null },
      is_own: false,
      created_at: '2025-06-01T11:00:00Z',
    },
  ],
});

const baseProps = {
  isMember: true,
  isJoining: false,
  discussions: [] as import('./GroupDiscussionTab').Discussion[],
  discussionsLoading: false,
  discussionsHasMore: false,
  expandedDiscussionId: null as number | null,
  expandedDiscussion: null as import('./GroupDiscussionTab').DiscussionDetail | null,
  expandedLoading: false,
  loadingEarlierReplies: false,
  replyContent: '',
  sendingReply: false,
  onJoinLeave: vi.fn(),
  onShowNewDiscussion: vi.fn(),
  onExpandDiscussion: vi.fn(),
  onLoadMoreDiscussions: vi.fn(),
  onLoadEarlierReplies: vi.fn(),
  onReplyContentChange: vi.fn(),
  onSendReply: vi.fn(),
};

describe('GroupDiscussionTab', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('shows locked state when user is not a member', async () => {
    const { GroupDiscussionTab } = await import('./GroupDiscussionTab');
    render(<GroupDiscussionTab {...baseProps} isMember={false} />);
    expect(screen.getByTestId('empty-state')).toBeInTheDocument();
  });

  it('shows join button when not a member and authenticated', async () => {
    const { GroupDiscussionTab } = await import('./GroupDiscussionTab');
    render(<GroupDiscussionTab {...baseProps} isMember={false} />);
    const btn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('join')
    );
    expect(btn).toBeInTheDocument();
  });

  it('calls onJoinLeave when join button is clicked', async () => {
    const onJoinLeave = vi.fn();
    const { GroupDiscussionTab } = await import('./GroupDiscussionTab');
    render(<GroupDiscussionTab {...baseProps} isMember={false} onJoinLeave={onJoinLeave} />);
    const btn = screen.getByRole('button', { name: /join/i });
    await userEvent.click(btn);
    expect(onJoinLeave).toHaveBeenCalled();
  });

  it('shows empty state when member but no discussions', async () => {
    const { GroupDiscussionTab } = await import('./GroupDiscussionTab');
    render(<GroupDiscussionTab {...baseProps} discussions={[]} />);
    expect(screen.getByTestId('empty-state')).toBeInTheDocument();
  });

  it('shows New Discussion button when member', async () => {
    const { GroupDiscussionTab } = await import('./GroupDiscussionTab');
    render(<GroupDiscussionTab {...baseProps} discussions={[makeDiscussion()]} />);
    const btn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('discussion')
    );
    expect(btn).toBeInTheDocument();
  });

  it('calls onShowNewDiscussion when new discussion button clicked', async () => {
    const onShowNewDiscussion = vi.fn();
    const { GroupDiscussionTab } = await import('./GroupDiscussionTab');
    render(
      <GroupDiscussionTab
        {...baseProps}
        discussions={[makeDiscussion()]}
        onShowNewDiscussion={onShowNewDiscussion}
      />
    );
    const newBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('discussion')
    );
    if (newBtn) await userEvent.click(newBtn);
    expect(onShowNewDiscussion).toHaveBeenCalled();
  });

  it('renders discussion titles for each discussion', async () => {
    const { GroupDiscussionTab } = await import('./GroupDiscussionTab');
    render(
      <GroupDiscussionTab
        {...baseProps}
        discussions={[
          makeDiscussion({ id: 1, title: 'Weekly check-in' }),
          makeDiscussion({ id: 2, title: 'New members intro' }),
        ]}
      />
    );
    expect(screen.getByText('Weekly check-in')).toBeInTheDocument();
    expect(screen.getByText('New members intro')).toBeInTheDocument();
  });

  it('renders the discussion reply count', async () => {
    const { GroupDiscussionTab } = await import('./GroupDiscussionTab');
    render(<GroupDiscussionTab {...baseProps} discussions={[makeDiscussion({ reply_count: 3 })]} />);

    expect(screen.getByText('3 replies')).toBeInTheDocument();
  });

  it('calls onExpandDiscussion when a discussion is clicked', async () => {
    const onExpandDiscussion = vi.fn();
    const disc = makeDiscussion({ id: 5 });

    const { GroupDiscussionTab } = await import('./GroupDiscussionTab');
    render(
      <GroupDiscussionTab
        {...baseProps}
        discussions={[disc]}
        onExpandDiscussion={onExpandDiscussion}
      />
    );
    // The discussion row has role="button"
    const discBtn = screen.getByRole('button', { name: /Weekly check-in/i });
    await userEvent.click(discBtn);
    expect(onExpandDiscussion).toHaveBeenCalledWith(5);
  });

  it('shows expanded discussion content when expandedDiscussionId matches', async () => {
    const disc = makeDiscussion({ id: 3, content: 'Discussion body here' });
    const expanded = makeExpandedDiscussion(disc);

    const { GroupDiscussionTab } = await import('./GroupDiscussionTab');
    render(
      <GroupDiscussionTab
        {...baseProps}
        discussions={[disc]}
        expandedDiscussionId={3}
        expandedDiscussion={expanded}
      />
    );

    await waitFor(() => {
      const safeHtmlEls = screen.getAllByTestId('safe-html');
      expect(safeHtmlEls.length).toBeGreaterThan(0);
    });
  });

  it('renders the root body exactly once and never conflates it with replies', async () => {
    const disc = makeDiscussion({ id: 3, content: 'Unique root body' });
    const expanded = makeExpandedDiscussion(disc);

    const { GroupDiscussionTab } = await import('./GroupDiscussionTab');
    render(
      <GroupDiscussionTab
        {...baseProps}
        discussions={[disc]}
        expandedDiscussionId={3}
        expandedDiscussion={expanded}
      />
    );

    const rootBodies = screen.getAllByTestId('safe-html')
      .filter((element) => element.textContent === 'Unique root body');
    expect(rootBodies).toHaveLength(1);
  });

  it('loads earlier replies only when the server supplies another reply cursor', async () => {
    const onLoadEarlierReplies = vi.fn();
    const disc = makeDiscussion({ id: 3 });
    const expanded = {
      ...makeExpandedDiscussion(disc),
      messagesHasMore: true,
      messagesNextCursor: 'older-page',
    };

    const { GroupDiscussionTab } = await import('./GroupDiscussionTab');
    render(
      <GroupDiscussionTab
        {...baseProps}
        discussions={[disc]}
        expandedDiscussionId={3}
        expandedDiscussion={expanded}
        onLoadEarlierReplies={onLoadEarlierReplies}
      />
    );

    await userEvent.click(screen.getByRole('button', { name: /load earlier replies/i }));
    expect(onLoadEarlierReplies).toHaveBeenCalledTimes(1);
  });

  it('shows reply messages when discussion is expanded', async () => {
    const disc = makeDiscussion({ id: 3 });
    const expanded = makeExpandedDiscussion(disc);

    const { GroupDiscussionTab } = await import('./GroupDiscussionTab');
    render(
      <GroupDiscussionTab
        {...baseProps}
        discussions={[disc]}
        expandedDiscussionId={3}
        expandedDiscussion={expanded}
      />
    );
    expect(screen.getByText('Bob')).toBeInTheDocument();
    // "Doing great!" is inside SafeHtml stub
    const safeHtmlEls = screen.getAllByTestId('safe-html');
    const contents = safeHtmlEls.map((el) => el.textContent);
    expect(contents.some((c) => c?.includes('Doing great!'))).toBe(true);
    const replyBody = safeHtmlEls.find((element) => element.textContent?.includes('Doing great!'));
    expect(replyBody?.closest('.border-s-2')).toHaveClass('ms-3', 'ps-3');
  });

  it('shows loading spinner when expandedLoading is true', async () => {
    const disc = makeDiscussion({ id: 3 });

    const { GroupDiscussionTab } = await import('./GroupDiscussionTab');
    render(
      <GroupDiscussionTab
        {...baseProps}
        discussions={[disc]}
        expandedDiscussionId={3}
        expandedDiscussion={null}
        expandedLoading={true}
      />
    );
    const spinners = screen.queryAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('shows reply textarea when discussion is expanded', async () => {
    const disc = makeDiscussion({ id: 3 });
    const expanded = makeExpandedDiscussion(disc);

    const { GroupDiscussionTab } = await import('./GroupDiscussionTab');
    render(
      <GroupDiscussionTab
        {...baseProps}
        discussions={[disc]}
        expandedDiscussionId={3}
        expandedDiscussion={expanded}
        replyContent=""
      />
    );
    const textarea = screen.getByRole('textbox', { name: /reply/i });
    expect(textarea).toBeInTheDocument();
  });

  it('calls onReplyContentChange when typing in reply textarea', async () => {
    const onReplyContentChange = vi.fn();
    const disc = makeDiscussion({ id: 3 });
    const expanded = makeExpandedDiscussion(disc);

    const { GroupDiscussionTab } = await import('./GroupDiscussionTab');
    render(
      <GroupDiscussionTab
        {...baseProps}
        discussions={[disc]}
        expandedDiscussionId={3}
        expandedDiscussion={expanded}
        replyContent=""
        onReplyContentChange={onReplyContentChange}
      />
    );
    const textarea = screen.getByRole('textbox', { name: /reply/i });
    fireEvent.change(textarea, { target: { value: 'Hello world' } });
    expect(onReplyContentChange).toHaveBeenCalledWith('Hello world');
  });

  it('send reply button is disabled when replyContent is empty', async () => {
    const disc = makeDiscussion({ id: 3 });
    const expanded = makeExpandedDiscussion(disc);

    const { GroupDiscussionTab } = await import('./GroupDiscussionTab');
    render(
      <GroupDiscussionTab
        {...baseProps}
        discussions={[disc]}
        expandedDiscussionId={3}
        expandedDiscussion={expanded}
        replyContent=""
      />
    );
    const sendBtn = screen.getByRole('button', { name: /send/i });
    expect(sendBtn).toBeDisabled();
  });

  it('send reply button is enabled when replyContent has text', async () => {
    const disc = makeDiscussion({ id: 3 });
    const expanded = makeExpandedDiscussion(disc);

    const { GroupDiscussionTab } = await import('./GroupDiscussionTab');
    render(
      <GroupDiscussionTab
        {...baseProps}
        discussions={[disc]}
        expandedDiscussionId={3}
        expandedDiscussion={expanded}
        replyContent="Hello!"
      />
    );
    const sendBtn = screen.getByRole('button', { name: /send/i });
    expect(sendBtn).not.toBeDisabled();
    expect(sendBtn).toHaveClass('min-h-11', 'min-w-11');
  });

  it('calls onSendReply when send button clicked with content', async () => {
    const onSendReply = vi.fn();
    const disc = makeDiscussion({ id: 3 });
    const expanded = makeExpandedDiscussion(disc);

    const { GroupDiscussionTab } = await import('./GroupDiscussionTab');
    render(
      <GroupDiscussionTab
        {...baseProps}
        discussions={[disc]}
        expandedDiscussionId={3}
        expandedDiscussion={expanded}
        replyContent="My reply"
        onSendReply={onSendReply}
      />
    );
    const sendBtn = screen.getByRole('button', { name: /send/i });
    await userEvent.click(sendBtn);
    expect(onSendReply).toHaveBeenCalled();
  });

  it('shows Load More button when discussionsHasMore is true', async () => {
    const { GroupDiscussionTab } = await import('./GroupDiscussionTab');
    render(
      <GroupDiscussionTab
        {...baseProps}
        discussions={[makeDiscussion()]}
        discussionsHasMore={true}
      />
    );
    const moreBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('more') ||
      b.textContent?.toLowerCase().includes('load')
    );
    expect(moreBtn).toBeInTheDocument();
  });

  it('calls onLoadMoreDiscussions when Load More is clicked', async () => {
    const onLoadMoreDiscussions = vi.fn();
    const { GroupDiscussionTab } = await import('./GroupDiscussionTab');
    render(
      <GroupDiscussionTab
        {...baseProps}
        discussions={[makeDiscussion()]}
        discussionsHasMore={true}
        onLoadMoreDiscussions={onLoadMoreDiscussions}
      />
    );
    const moreBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('more') ||
      b.textContent?.toLowerCase().includes('load')
    );
    if (moreBtn) await userEvent.click(moreBtn);
    expect(onLoadMoreDiscussions).toHaveBeenCalled();
  });

  it('shows initial discussions loading spinner', async () => {
    const { GroupDiscussionTab } = await import('./GroupDiscussionTab');
    render(
      <GroupDiscussionTab
        {...baseProps}
        discussions={[]}
        discussionsLoading={true}
      />
    );
    const spinners = screen.queryAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders SocialInteractionPanel inside expanded discussion', async () => {
    const disc = makeDiscussion({ id: 3 });
    const expanded = makeExpandedDiscussion(disc);

    const { GroupDiscussionTab } = await import('./GroupDiscussionTab');
    render(
      <GroupDiscussionTab
        {...baseProps}
        discussions={[disc]}
        expandedDiscussionId={3}
        expandedDiscussion={expanded}
      />
    );
    expect(screen.getByTestId('social-panel')).toBeInTheDocument();
    expect(mockSocialPanel).toHaveBeenCalledWith(expect.objectContaining({
      targetType: 'discussion',
      targetId: 3,
      allowComments: false,
    }));
  });
});
