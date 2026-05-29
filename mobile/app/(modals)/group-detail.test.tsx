// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';

// --- Mocks ---

const mockRouterPush = jest.fn();

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn(), back: jest.fn() }),
  router: { push: (...args: unknown[]) => mockRouterPush(...args), replace: jest.fn(), back: jest.fn() },
  useLocalSearchParams: () => ({ id: '1' }),
  useNavigation: () => ({ setOptions: jest.fn() }),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'detail.title': 'Group Detail',
        'detail.invalidId': 'Invalid group ID.',
        'detail.goBack': 'Go back',
        'detail.notFound': 'Group not found.',
        'detail.about': 'About',
        'detail.members': 'Members',
        'detail.admin': 'Group admin',
        'detail.groupAdmin': 'Group admin',
        'detail.emptyAbout': 'No description.',
        'detail.emptyDiscussions': 'No discussions.',
        'detail.emptyMembers': 'No members.',
        'detail.emptyAnnouncements': 'No announcements.',
        'detail.joinToDiscuss': 'Join to discuss.',
        'detail.joinToSeeMembers': 'Join to see members.',
        'detail.joinToSeeAnnouncements': 'Join to see announcements.',
        'detail.pinned': 'Pinned',
        'detail.newAnnouncement': 'New announcement',
        'detail.newAnnouncementHint': 'Post an update.',
        'detail.createAnnouncement': 'Create',
        'detail.announcementTitlePlaceholder': 'Announcement title',
        'detail.announcementContentPlaceholder': 'Write the announcement...',
        'detail.pinAnnouncement': 'Pin announcement',
        'detail.unpinAnnouncement': 'Unpin',
        'detail.publishAnnouncement': 'Publish announcement',
        'detail.announcementRequired': 'Add a title and message.',
        'detail.announcementCreateError': 'Could not create announcement.',
        'detail.announcementUpdateError': 'Could not update announcement.',
        'detail.announcementDeleteError': 'Could not delete announcement.',
        'detail.deleteAnnouncement': 'Delete',
        'detail.deleteAnnouncementTitle': 'Delete announcement',
        'detail.deleteAnnouncementMessage': 'Delete this announcement?',
        'detail.startDiscussion': 'Start a discussion',
        'detail.startDiscussionHint': 'Ask a question.',
        'detail.newDiscussion': 'New',
        'detail.discussionTitlePlaceholder': 'Discussion title',
        'detail.discussionContentPlaceholder': 'Write a message',
        'detail.publishDiscussion': 'Publish discussion',
        'detail.discussionRequired': 'Add a title and message.',
        'detail.discussionCreateError': 'Could not create discussion.',
        'detail.replies': opts ? `${String(opts.count ?? 0)} replies` : '0 replies',
        'detail.tabs.overview': 'Overview',
        'detail.tabs.discussion': 'Discussions',
        'detail.tabs.members': 'Members',
        'detail.tabs.events': 'Events',
        'detail.tabs.announcements': 'Announcements',
        'detail.tabs.files': 'Files',
        'detail.tabs.qa': 'Q&A',
        'detail.files.title': 'Group files',
        'detail.files.subtitle': 'Documents and resources.',
        'detail.files.empty': 'No files yet.',
        'detail.files.joinToView': 'Join to view files.',
        'detail.files.download': 'Download',
        'detail.files.downloadLabel': opts ? `Download ${String(opts.name ?? '')}` : 'Download file',
        'detail.qa.title': 'Group Q&A',
        'detail.qa.subtitle': 'Ask practical questions.',
        'detail.qa.ask': 'Ask',
        'detail.qa.titlePlaceholder': 'Question title',
        'detail.qa.bodyPlaceholder': 'Add context...',
        'detail.qa.publish': 'Publish question',
        'detail.qa.validation': 'Add a question title and details.',
        'detail.qa.createError': 'Could not create question.',
        'detail.qa.loadError': 'Could not load question.',
        'detail.qa.answerPlaceholder': 'Write an answer...',
        'detail.qa.postAnswer': 'Post answer',
        'detail.qa.answerValidation': 'Write an answer.',
        'detail.qa.answerError': 'Could not post answer.',
        'detail.qa.empty': 'No questions yet.',
        'detail.qa.joinToView': 'Join to view Q&A.',
        'detail.qa.answered': 'Answered',
        'detail.qa.accepted': 'Accepted',
        'detail.qa.noAnswers': 'No answers yet.',
        'detail.qa.answers': opts ? `${String(opts.count ?? 0)} answers` : '0 answers',
        'detail.qa.votes': opts ? `${String(opts.count ?? 0)} votes` : '0 votes',
        'detail.eventsHeading': 'Group events',
        'detail.eventsSubtitle': 'Events connected to this group.',
        'detail.emptyEvents': 'No events yet.',
        'detail.eventAttending': opts ? `${String(opts.count ?? 0)} going` : '0 going',
        'detail.eventOnline': 'Online',
        'detail.roles.owner': 'Owner',
        'detail.roles.admin': 'Admin',
        'detail.roles.member': 'Member',
        'detail.stats.members': 'Members',
        'detail.stats.posts': 'Posts',
        'detail.ownerTools': 'Group tools',
        'detail.edit': 'Edit group',
        'featured': 'Featured',
        'private': 'Private',
        'public': 'Public',
        'join': 'Join',
        'leave': 'Leave',
        'joined': 'Joined',
        'leaveConfirmTitle': 'Leave group?',
        'leaveConfirmMessage': 'Are you sure you want to leave?',
        'joinError': 'Failed to join.',
        'leaveError': 'Failed to leave.',
        'members': opts ? `${String(opts.count ?? 0)} members` : '0 members',
        'posts': opts ? `${String(opts.count ?? 0)} posts` : '0 posts',
        'common:buttons.cancel': 'Cancel',
        'common:errors.alertTitle': 'Error',
      };
      return map[key] ?? key;
    },
    i18n: { language: 'en' },
  }),
}));

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#6366f1',
  useTenant: () => ({ hasFeature: () => true }),
}));

jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    bg: '#ffffff',
    surface: '#f8f9fa',
    text: '#000000',
    textSecondary: '#666666',
    textMuted: '#999999',
    border: '#dddddd',
    borderSubtle: '#eeeeee',
    error: '#e53e3e',
    success: '#16a34a',
  }),
}));

const mockUseApi = jest.fn();
jest.mock('@/lib/hooks/useApi', () => ({
  useApi: (...args: unknown[]) => mockUseApi(...args),
}));

jest.mock('expo-haptics', () => ({
  impactAsync: jest.fn().mockResolvedValue(undefined),
  ImpactFeedbackStyle: { Light: 'light' },
}));

jest.mock('@/lib/haptics', () => ({
  impactAsync: jest.fn().mockResolvedValue(undefined),
  notificationAsync: jest.fn().mockResolvedValue(undefined),
  ImpactFeedbackStyle: { Light: 'light' },
  NotificationFeedbackType: { Success: 'success', Error: 'error' },
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/lib/api/groups', () => ({
  getGroup: jest.fn(),
  createGroupDiscussion: jest.fn().mockResolvedValue({ data: {} }),
  getGroupMembers: jest.fn(),
  getGroupDiscussions: jest.fn(),
  getGroupAnnouncements: jest.fn(),
  getGroupFiles: jest.fn(),
  getGroupQuestions: jest.fn(),
  getGroupQuestion: jest.fn().mockResolvedValue({ data: { id: 44, title: 'How should we compost?', answers: [] } }),
  createGroupQuestion: jest.fn().mockResolvedValue({ data: { id: 44, title: 'How should we compost?' } }),
  answerGroupQuestion: jest.fn().mockResolvedValue({ data: { id: 55, question_id: 44 } }),
  createGroupAnnouncement: jest.fn().mockResolvedValue({ data: {} }),
  updateGroupAnnouncement: jest.fn().mockResolvedValue({ data: {} }),
  deleteGroupAnnouncement: jest.fn().mockResolvedValue({ data: { deleted: true } }),
  joinGroup: jest.fn().mockResolvedValue({}),
  leaveGroup: jest.fn().mockResolvedValue({}),
}));

jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/ui/LoadingSpinner', () => () => null);

// --- Tests ---

import GroupDetailScreen from './group-detail';
import { answerGroupQuestion, createGroupAnnouncement, createGroupQuestion, joinGroup, updateGroupAnnouncement } from '@/lib/api/groups';

const defaultApiState = { data: null, isLoading: true, error: null, refresh: jest.fn() };

beforeEach(() => {
  mockUseApi.mockReturnValue(defaultApiState);
  mockRouterPush.mockClear();
  jest.clearAllMocks();
});

const mockGroupDetail = {
  id: 1,
  name: 'Garden Club',
  description: 'A club for gardening enthusiasts.',
  long_description: null,
  visibility: 'public' as const,
  member_count: 12,
  posts_count: 5,
  is_featured: false,
  is_member: false,
  tags: [],
  admin: {
    id: 10,
    name: 'Alice Admin',
    avatar_url: null,
  },
};

describe('GroupDetailScreen', () => {
  it('renders loading spinner when data is loading', () => {
    // Default mock: isLoading=true, data=null — LoadingSpinner is mocked to null
    // The screen renders a SafeAreaView with LoadingSpinner (null); we verify no content shown
    const { queryByText } = render(<GroupDetailScreen />);
    expect(queryByText('Garden Club')).toBeNull();
    expect(queryByText('Group not found.')).toBeNull();
  });

  it('renders not-found message when data is null and not loading', () => {
    mockUseApi.mockReturnValue({
      data: null,
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getAllByText } = render(<GroupDetailScreen />);
    expect(getAllByText('Group not found.').length).toBeGreaterThan(0);
  });

  it('renders group name when data is loaded', () => {
    mockUseApi.mockReturnValue({
      data: { data: mockGroupDetail },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<GroupDetailScreen />);
    expect(getByText('Garden Club')).toBeTruthy();
  });

  it('renders join button when user is not a member', () => {
    mockUseApi.mockReturnValue({
      data: { data: { ...mockGroupDetail, is_member: false } },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<GroupDetailScreen />);
    expect(getByText('Join')).toBeTruthy();
  });

  it('renders leave button when user is already a member', () => {
    mockUseApi.mockReturnValue({
      data: { data: { ...mockGroupDetail, is_member: true } },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<GroupDetailScreen />);
    expect(getByText('Leave')).toBeTruthy();
  });

  it('renders group description when loaded', () => {
    mockUseApi.mockReturnValue({
      data: { data: mockGroupDetail },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<GroupDetailScreen />);
    expect(getByText('A club for gardening enthusiasts.')).toBeTruthy();
  });

  it('joins a public group from the detail page', async () => {
    mockUseApi.mockReturnValue({
      data: { data: { ...mockGroupDetail, is_member: false } },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<GroupDetailScreen />);

    fireEvent.press(getByText('Join'));

    await waitFor(() => {
      expect(joinGroup).toHaveBeenCalledWith(1);
      expect(getByText('Leave')).toBeTruthy();
    });
  });

  it('shows an edit action for group admins', () => {
    mockUseApi.mockReturnValue({
      data: {
        data: {
          ...mockGroupDetail,
          is_member: true,
          viewer_membership: { status: 'active', role: 'admin', is_admin: true },
        },
      },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<GroupDetailScreen />);

    fireEvent.press(getByText('Edit group'));

    expect(mockRouterPush).toHaveBeenCalledWith({ pathname: '/(modals)/edit-group', params: { id: '1' } });
  });

  it('opens group event details from HeroUI Native-backed event cards', () => {
    const groupState = {
      data: { data: { ...mockGroupDetail, is_member: true } },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    };
    const emptyListState = { data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() };
    const emptyAnnouncementsState = { data: { data: { items: [] } }, isLoading: false, error: null, refresh: jest.fn() };
    const eventsState = {
      data: {
        data: [
          {
            id: 77,
            title: 'Seed swap',
            description: 'Bring seeds to share.',
            start_date: '2026-06-01T12:00:00Z',
            location: 'Community hall',
            is_online: false,
            attendees_count: 4,
          },
        ],
      },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    };
    let apiCall = 0;
    mockUseApi.mockImplementation(() => {
      const emptyFilesState = { data: { data: { items: [], cursor: null, has_more: false } }, isLoading: false, error: null, refresh: jest.fn() };
      const states = [groupState, emptyListState, emptyListState, emptyAnnouncementsState, emptyFilesState, eventsState];
      const state = states[apiCall % states.length];
      apiCall += 1;
      return state;
    });

    const { getByText } = render(<GroupDetailScreen />);

    fireEvent.press(getByText('Events'));
    fireEvent.press(getByText('Seed swap'));

    expect(mockRouterPush).toHaveBeenCalledWith({
      pathname: '/(modals)/event-detail',
      params: { id: '77' },
    });
  });

  it('lets group admins create announcements from the native announcements tab', async () => {
    const refreshAnnouncements = jest.fn();
    const groupState = {
      data: {
        data: {
          ...mockGroupDetail,
          is_member: true,
          viewer_membership: { status: 'active', role: 'admin', is_admin: true },
        },
      },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    };
    const emptyListState = { data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() };
    const announcementsState = {
      data: { data: { items: [], cursor: null, has_more: false } },
      isLoading: false,
      error: null,
      refresh: refreshAnnouncements,
    };
    const eventsState = { data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() };
    let apiCall = 0;
    mockUseApi.mockImplementation(() => {
      const emptyFilesState = { data: { data: { items: [], cursor: null, has_more: false } }, isLoading: false, error: null, refresh: jest.fn() };
      const states = [groupState, emptyListState, emptyListState, announcementsState, emptyFilesState, eventsState];
      const state = states[apiCall % states.length];
      apiCall += 1;
      return state;
    });

    const { getByPlaceholderText, getByText } = render(<GroupDetailScreen />);

    fireEvent.press(getByText('Announcements'));
    fireEvent.press(getByText('Create'));
    fireEvent.changeText(getByPlaceholderText('Announcement title'), 'Spring update');
    fireEvent.changeText(getByPlaceholderText('Write the announcement...'), 'Seeds arrive Friday.');
    fireEvent.press(getByText('Pin announcement'));
    fireEvent.press(getByText('Publish announcement'));

    await waitFor(() => {
      expect(createGroupAnnouncement).toHaveBeenCalledWith(1, {
        title: 'Spring update',
        content: 'Seeds arrive Friday.',
        is_pinned: true,
      });
      expect(refreshAnnouncements).toHaveBeenCalled();
    });
  });

  it('lets group admins toggle announcement pinning', async () => {
    const groupState = {
      data: {
        data: {
          ...mockGroupDetail,
          is_member: true,
          viewer_membership: { status: 'active', role: 'admin', is_admin: true },
        },
      },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    };
    const emptyListState = { data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() };
    const announcementsState = {
      data: {
        data: {
          items: [{
            id: 22,
            title: 'Pinned note',
            content: 'Remember the meet-up.',
            is_pinned: true,
            priority: 0,
            is_expired: false,
            author: { id: 10, name: 'Alice Admin', avatar_url: null },
            created_at: '2026-06-01T00:00:00Z',
            updated_at: null,
            expires_at: null,
          }],
          cursor: null,
          has_more: false,
        },
      },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    };
    const eventsState = { data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() };
    let apiCall = 0;
    mockUseApi.mockImplementation(() => {
      const emptyFilesState = { data: { data: { items: [], cursor: null, has_more: false } }, isLoading: false, error: null, refresh: jest.fn() };
      const states = [groupState, emptyListState, emptyListState, announcementsState, emptyFilesState, eventsState];
      const state = states[apiCall % states.length];
      apiCall += 1;
      return state;
    });

    const { getByText } = render(<GroupDetailScreen />);

    fireEvent.press(getByText('Announcements'));
    fireEvent.press(getByText('Unpin'));

    await waitFor(() => {
      expect(updateGroupAnnouncement).toHaveBeenCalledWith(1, 22, { is_pinned: false });
    });
  });

  it('renders group files in the native files tab', () => {
    const groupState = {
      data: { data: { ...mockGroupDetail, is_member: true } },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    };
    const emptyListState = { data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() };
    const emptyAnnouncementsState = { data: { data: { items: [], cursor: null, has_more: false } }, isLoading: false, error: null, refresh: jest.fn() };
    const filesState = {
      data: {
        data: {
          items: [{
            id: 31,
            group_id: 1,
            file_name: 'Planting guide.pdf',
            file_type: 'application/pdf',
            file_size: 2048,
            uploaded_by: 10,
            uploader_name: 'Alice Admin',
            uploader_avatar: null,
            folder: 'Guides',
            description: 'Spring planting checklist.',
            created_at: '2026-06-01T00:00:00Z',
          }],
          cursor: null,
          has_more: false,
        },
      },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    };
    const eventsState = { data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() };
    let apiCall = 0;
    mockUseApi.mockImplementation(() => {
      const states = [groupState, emptyListState, emptyListState, emptyAnnouncementsState, filesState, eventsState];
      const state = states[apiCall % states.length];
      apiCall += 1;
      return state;
    });

    const { getByText } = render(<GroupDetailScreen />);

    fireEvent.press(getByText('Files'));

    expect(getByText('Group files')).toBeTruthy();
    expect(getByText('Planting guide.pdf')).toBeTruthy();
    expect(getByText('Spring planting checklist.')).toBeTruthy();
    expect(getByText('Download')).toBeTruthy();
  });
});
