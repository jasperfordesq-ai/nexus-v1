// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { Alert } from 'react-native';
import { render, fireEvent } from '@testing-library/react-native';

// --- Mocks ---

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn(), back: jest.fn(), canGoBack: jest.fn(() => false) }),
  router: { push: jest.fn(), replace: jest.fn(), back: jest.fn(), canGoBack: jest.fn(() => false) },
  useLocalSearchParams: () => ({}),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'common:back': 'Back',
        'title': 'Skills & Endorsements',
        'heroEyebrow': 'Trust network',
        'subtitle': 'Show the skills you can offer.',
        'mySkills': 'My Skills',
        'skillsIntro': 'Keep your profile skills current.',
        'endorsementsIntro': 'Endorsements from other members.',
        'skillsCount': opts ? `${String(opts.count ?? 0)} skills` : '0 skills',
        'endorsementsCount': opts ? `${String(opts.count ?? 0)} endorsements` : '0 endorsements',
        'endorsements': 'Endorsements',
        'noSkills': 'No skills added yet.',
        'noSkillsHint': 'Add skills you can share.',
        'noEndorsements': 'No endorsements yet.',
        'noEndorsementsHint': 'When members endorse your skills, they will appear here.',
        'addSkill': 'Add Skill',
        'removeSkill': 'Remove',
        'skillPlaceholder': 'Enter skill name…',
        'addSkillErrorTitle': 'Skill not added',
        'addSkillError': 'Failed to save skill.',
        'removeSkillTitle': 'Remove skill',
        'removeSkillConfirm': 'Remove this skill?',
        'endorsedBy': opts ? `Endorsed by ${String(opts.count ?? 0)}` : 'Endorsed by 0',
        'common:cancel': 'Cancel',
        'skillRemovedTitle': 'Skill removed',
        'skillRemoved': 'Skill removed.',
        'removeSkillErrorTitle': 'Skill not removed',
        'removeSkillError': 'Could not remove skill.',
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
    success: '#22c55e',
  }),
}));

jest.mock('@/lib/hooks/useAuth', () => ({
  useAuth: () => ({ user: { id: 1, name: 'Test User' } }),
}));

const mockUseApi = jest.fn();
jest.mock('@/lib/hooks/useApi', () => ({
  useApi: (...args: unknown[]) => mockUseApi(...args),
}));

jest.mock('expo-haptics', () => ({
  impactAsync: jest.fn().mockResolvedValue(undefined),
  notificationAsync: jest.fn().mockResolvedValue(undefined),
  ImpactFeedbackStyle: { Light: 'light', Medium: 'medium' },
  NotificationFeedbackType: { Success: 'success', Error: 'error' },
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/lib/api/endorsements', () => ({
  addSkill: jest.fn().mockResolvedValue(undefined),
  getMySkills: jest.fn(),
  getUserEndorsements: jest.fn(),
  removeSkill: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/ui/LoadingSpinner', () => () => null);

// --- Tests ---

import EndorsementsScreen from './endorsements';
import { removeSkill } from '@/lib/api/endorsements';

const defaultApiState = { data: null, isLoading: false, error: null, refresh: jest.fn() };

beforeEach(() => {
  mockUseApi.mockReturnValue(defaultApiState);
  jest.spyOn(Alert, 'alert').mockImplementation(jest.fn());
  (removeSkill as jest.Mock).mockClear();
});

afterEach(() => {
  jest.restoreAllMocks();
});

const mockSkill = {
  id: 1,
  name: 'JavaScript',
  category: 'Technology',
};

const mockEndorsement = {
  id: 10,
  skill: { id: 1, name: 'JavaScript' },
  endorsed_by: { id: 2, name: 'Alice', avatar: null },
  message: 'Great developer!',
  created_at: '2026-01-20T10:00:00Z',
};

describe('EndorsementsScreen', () => {
  it('renders without crashing', () => {
    const { toJSON } = render(<EndorsementsScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders the My Skills and Endorsements tab buttons', () => {
    const { getByText } = render(<EndorsementsScreen />);
    expect(getByText('My Skills')).toBeTruthy();
    expect(getByText('Endorsements')).toBeTruthy();
  });

  it('renders the empty skills state on the Skills tab', () => {
    const { getByText } = render(<EndorsementsScreen />);
    expect(getByText('No skills added yet.')).toBeTruthy();
  });

  it('renders the Add Skill button on the Skills tab', () => {
    const { getByText } = render(<EndorsementsScreen />);
    expect(getByText('Add Skill')).toBeTruthy();
  });

  it('renders skill cards when skills data is available', () => {
    mockUseApi
      .mockReturnValueOnce({ data: { data: { skills: [mockSkill] } }, isLoading: false, error: null, refresh: jest.fn() })
      .mockReturnValueOnce({ data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<EndorsementsScreen />);
    expect(getByText('JavaScript')).toBeTruthy();
    expect(getByText('Technology')).toBeTruthy();
  });

  it('switches to the Endorsements tab and renders endorsement cards', () => {
    let callCount = 0;
    mockUseApi.mockImplementation(() => {
      callCount += 1;
      if (callCount % 2 === 1) {
        return { data: { data: { skills: [] } }, isLoading: false, error: null, refresh: jest.fn() };
      }
      return { data: { data: [mockEndorsement] }, isLoading: false, error: null, refresh: jest.fn() };
    });

    const { getByText } = render(<EndorsementsScreen />);
    fireEvent.press(getByText('Endorsements'));
    expect(getByText('Alice')).toBeTruthy();
    expect(getByText('Great developer!')).toBeTruthy();
  });

  it('renders empty endorsements state when switching to Endorsements tab with no data', () => {
    const { getByText } = render(<EndorsementsScreen />);
    fireEvent.press(getByText('Endorsements'));
    expect(getByText('No endorsements yet.')).toBeTruthy();
  });

  it('uses translated titles when confirming and completing skill removal', async () => {
    const refresh = jest.fn();
    mockUseApi
      .mockReturnValueOnce({ data: { data: { skills: [mockSkill] } }, isLoading: false, error: null, refresh })
      .mockReturnValueOnce({ data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByLabelText } = render(<EndorsementsScreen />);
    fireEvent.press(getByLabelText('Remove'));

    expect(Alert.alert).toHaveBeenCalledWith(
      'Remove skill',
      'Remove this skill?',
      expect.arrayContaining([
        expect.objectContaining({ text: 'Remove' }),
      ]),
    );

    const removeButton = (Alert.alert as jest.Mock).mock.calls[0][2][1];
    await removeButton.onPress();

    expect(removeSkill).toHaveBeenCalledWith(1);
    expect(Alert.alert).toHaveBeenLastCalledWith('Skill removed', 'Skill removed.');
  });
});
