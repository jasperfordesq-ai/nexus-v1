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
        title: 'My Goals',
        subtitle: 'Set personal milestones, track progress, and keep your momentum visible.',
        heroEyebrow: 'Personal progress',
        'goals:addGoal': 'Add Goal',
        addGoal: 'Add Goal',
        'goals:empty': 'No goals yet. Add one to get started!',
        'goals:noGoals': 'No goals yet',
        noGoals: 'No goals yet',
        'goals:noGoalsHint': 'Create your first goal to start tracking your progress.',
        noGoalsHint: 'Create your first goal to start tracking your progress.',
        'goals:abandonTitle': 'Abandon goal?',
        abandonTitle: 'Abandon goal?',
        'goals:abandonMessage': 'This cannot be undone.',
        abandonMessage: 'This cannot be undone.',
        percent: opts ? `${String(opts.percent ?? 0)}%` : '0%',
        momentumOn: 'Building',
        momentumEmpty: 'Ready',
        'stats.active': 'Active',
        'stats.completed': 'Completed',
        'stats.total': 'Total',
        'stats.momentum': 'Momentum',
        'stats.averageProgress': 'Average progress',
        'goals:create.title': 'New Goal',
        'create.title': 'New Goal',
        'create.subtitle': 'Give yourself a clear target to work toward.',
        'create.close': 'Close goal form',
        'goals:create.titleLabel': 'Title',
        'create.titleLabel': 'Title',
        'goals:create.titlePlaceholder': 'What do you want to achieve?',
        'create.titlePlaceholder': 'What do you want to achieve?',
        'goals:create.targetHoursLabel': 'Target Hours',
        'create.targetHoursLabel': 'Target Hours',
        'create.descriptionLabel': 'Description',
        'create.descriptionPlaceholder': 'Add a little context or a first step.',
        'create.targetPlaceholder': 'e.g. 10',
        'goals:create.submit': 'Create Goal',
        'create.submit': 'Create Goal',
        'goals:create.error': 'Failed to create goal.',
        'create.error': 'Failed to create goal.',
        'goals:complete': 'Mark complete',
        complete: 'Mark complete',
        'goals:abandon': 'Abandon',
        abandon: 'Abandon',
        'goals:updateError': 'Failed to update goal.',
        updateError: 'Failed to update goal.',
        'goals:progress': opts ? `${String(opts.current ?? 0)} / ${String(opts.target ?? 0)} hrs` : '0 / 0 hrs',
        progress: opts ? `${String(opts.current ?? 0)} / ${String(opts.target ?? 0)} hrs` : '0 / 0 hrs',
        'goals:noTarget': opts ? `${String(opts.current ?? 0)} hrs` : '0 hrs',
        noTarget: opts ? `${String(opts.current ?? 0)} hrs` : '0 hrs',
        'goals:due': opts ? `Due ${String(opts.date ?? '')}` : 'Due',
        due: opts ? `Due ${String(opts.date ?? '')}` : 'Due',
        'goals:status.active': 'Active',
        'status.active': 'Active',
        'goals:status.completed': 'Completed',
        'status.completed': 'Completed',
        'goals:status.abandoned': 'Abandoned',
        'status.abandoned': 'Abandoned',
        'visibility.public': 'Public',
        buddy: opts ? `Buddy: ${String(opts.name ?? '')}` : 'Buddy',
        'common:cancel': 'Cancel',
        'common:back': 'Back',
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

    const { getAllByText } = render(<GoalsScreen />);
    expect(getAllByText('Active').length).toBeGreaterThan(0);
  });

  it('renders Complete and Abandon action buttons on active goal cards', () => {
    mockUseApi.mockReturnValue({ data: { data: [mockGoal] }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<GoalsScreen />);
    expect(getByText('Mark complete')).toBeTruthy();
    expect(getByText('Abandon')).toBeTruthy();
  });

  it('renders Completed status badge and no action buttons on completed goals', () => {
    mockUseApi.mockReturnValue({ data: { data: [mockCompletedGoal] }, isLoading: false, error: null, refresh: jest.fn() });

    const { getAllByText, queryByText } = render(<GoalsScreen />);
    expect(getAllByText('Completed').length).toBeGreaterThan(0);
    expect(queryByText('Mark complete')).toBeNull();
    expect(queryByText('Abandon')).toBeNull();
  });
});
