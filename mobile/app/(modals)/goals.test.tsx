// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';

// --- Mocks ---

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn(), back: jest.fn() }),
  router: { push: jest.fn(), replace: jest.fn(), back: jest.fn() },
  useLocalSearchParams: () => ({}),
  useNavigation: () => ({ setOptions: jest.fn() }),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'goals:title': 'My Goals',
        'goals:addGoal': 'Add Goal',
        'goals:empty': 'No goals yet. Add one to get started!',
        'goals:noGoals': 'No goals yet',
        'goals:noGoalsHint': 'Create your first goal to start tracking your progress.',
        'goals:abandonTitle': 'Abandon goal?',
        'goals:abandonMessage': 'This cannot be undone.',
        'goals:create.title': 'New Goal',
        'goals:create.titleLabel': 'Title',
        'goals:create.titlePlaceholder': 'What do you want to achieve?',
        'goals:create.targetHoursLabel': 'Target Hours',
        'goals:create.submit': 'Create Goal',
        'goals:create.error': 'Failed to create goal.',
        'goals:complete': 'Mark complete',
        'goals:abandon': 'Abandon',
        'goals:updateError': 'Failed to update goal.',
        'goals:progress': opts ? `${String(opts.current ?? 0)} / ${String(opts.target ?? 0)} hrs` : '0 / 0 hrs',
        'goals:noTarget': opts ? `${String(opts.current ?? 0)} hrs` : '0 hrs',
        'goals:due': opts ? `Due ${String(opts.date ?? '')}` : 'Due',
        'goals:status.active': 'Active',
        'goals:status.completed': 'Completed',
        'goals:status.abandoned': 'Abandoned',
        'common:cancel': 'Cancel',
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
    success: '#22c55e',
  }),
}));

const mockUseApi = jest.fn();
jest.mock('@/lib/hooks/useApi', () => ({
  useApi: (...args: unknown[]) => mockUseApi(...args),
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/lib/api/goals', () => ({
  getGoals: jest.fn(),
  createGoal: jest.fn().mockResolvedValue({ data: { id: 99, title: 'Learn React Native', status: 'active', progress_hours: 0, target_hours: 10, due_date: null } }),
  updateGoalStatus: jest.fn().mockResolvedValue({ data: {} }),
}));

jest.mock('@/components/ui/LoadingSpinner', () => () => null);

// --- Tests ---

import GoalsScreen from './goals';

const defaultApiState = { data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() };

beforeEach(() => {
  mockUseApi.mockReturnValue(defaultApiState);
});

const mockGoal = {
  id: 1,
  title: 'Learn React Native',
  status: 'active' as const,
  progress_hours: 3,
  target_hours: 10,
  due_date: '2026-06-01',
  created_at: '2026-01-01T00:00:00Z',
};

const mockCompletedGoal = {
  id: 2,
  title: 'Finish Community Garden',
  status: 'completed' as const,
  progress_hours: 8,
  target_hours: 8,
  due_date: null,
  created_at: '2026-01-15T00:00:00Z',
};

describe('GoalsScreen', () => {
  it('renders without crashing', () => {
    const { toJSON } = render(<GoalsScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders the empty state when there are no goals', () => {
    const { getByText } = render(<GoalsScreen />);
    expect(getByText('No goals yet')).toBeTruthy();
  });

  it('renders a loading spinner when data is loading', () => {
    mockUseApi.mockReturnValueOnce({ data: null, isLoading: true, error: null, refresh: jest.fn() });

    const { toJSON } = render(<GoalsScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders goal cards when goals are available', () => {
    mockUseApi.mockReturnValue({ data: { data: [mockGoal] }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<GoalsScreen />);
    expect(getByText('Learn React Native')).toBeTruthy();
  });

  it('renders active status badge on an active goal', () => {
    mockUseApi.mockReturnValue({ data: { data: [mockGoal] }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<GoalsScreen />);
    expect(getByText('Active')).toBeTruthy();
  });

  it('renders Complete and Abandon action buttons on active goal cards', () => {
    mockUseApi.mockReturnValue({ data: { data: [mockGoal] }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<GoalsScreen />);
    expect(getByText('Mark complete')).toBeTruthy();
    expect(getByText('Abandon')).toBeTruthy();
  });

  it('renders Completed status badge and no action buttons on completed goals', () => {
    mockUseApi.mockReturnValue({ data: { data: [mockCompletedGoal] }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText, queryByText } = render(<GoalsScreen />);
    expect(getByText('Completed')).toBeTruthy();
    expect(queryByText('Mark complete')).toBeNull();
    expect(queryByText('Abandon')).toBeNull();
  });
});
