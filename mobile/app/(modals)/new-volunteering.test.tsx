// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { Alert } from 'react-native';
import { fireEvent, render, waitFor } from '@testing-library/react-native';

const mockUseApi = jest.fn();
const mockCreateOpportunity = jest.fn();
const mockReplace = jest.fn();

jest.mock('expo-router', () => ({
  router: {
    back: jest.fn(),
    replace: (...args: unknown[]) => mockReplace(...args),
  },
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'create.eyebrow': 'New opportunity',
        'create.title': 'Create Opportunity',
        'create.subtitle': 'Publish a volunteer role.',
        'create.organisationLabel': 'Organisation',
        'create.selectedOrganisation': `Posting for ${String(opts?.name ?? '')}`,
        'create.noOrganisations': 'You need an approved organisation.',
        'create.titleLabel': 'Title',
        'create.titlePlaceholder': 'What help do you need?',
        'create.descriptionLabel': 'Description',
        'create.descriptionPlaceholder': 'Describe the role, support, and expected impact.',
        'create.locationLabel': 'Location',
        'create.locationPlaceholder': 'Where will this happen?',
        'create.skillsLabel': 'Skills needed',
        'create.skillsPlaceholder': 'Comma-separated skills',
        'create.startLabel': 'Start date',
        'create.endLabel': 'End date',
        'create.datePlaceholder': 'YYYY-MM-DD',
        'create.remote': 'Remote opportunity',
        'create.reviewTitle': 'Ready to publish?',
        'create.reviewSubtitle': 'Check before posting.',
        'create.submit': 'Create opportunity',
        'create.validationTitle': 'Check opportunity details',
        'create.validationRequired': 'Choose an organisation and add a title and description.',
        'create.validationTitleMinLength': 'Use at least 5 characters for the title.',
        'create.validationDescriptionMinLength': 'Use at least 20 characters for the description.',
        'create.validationEndAfterStart': 'Use an end date after the start date.',
        'create.failedTitle': 'Opportunity not created',
        'create.failedDescription': 'We could not create the opportunity.',
        'common:back': 'Back',
      };
      return map[key] ?? key;
    },
    i18n: { language: 'en' },
  }),
}));

jest.mock('@/lib/hooks/useApi', () => ({
  useApi: (...args: unknown[]) => mockUseApi(...args),
}));

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#6366f1',
}));

jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    bg: '#ffffff',
    text: '#000000',
    textSecondary: '#666666',
    textMuted: '#999999',
    border: '#dddddd',
  }),
}));

jest.mock('@/lib/api/volunteering', () => ({
  createOpportunity: (...args: unknown[]) => mockCreateOpportunity(...args),
  getMyOrganisations: jest.fn(),
}));

jest.mock('@/lib/haptics', () => ({
  notificationAsync: jest.fn().mockResolvedValue(undefined),
  NotificationFeedbackType: { Success: 'success' },
}));

jest.mock('@expo/vector-icons', () => ({ Ionicons: 'View' }));
jest.mock('@/components/ui/AppTopBar', () => 'View');
jest.mock('@/components/ModalErrorBoundary', () => ({ children }: { children: React.ReactNode }) => <>{children}</>);
jest.mock('@/components/ui/FormActionFooter', () => {
  const React = require('react');
  const { Pressable, Text, View } = require('react-native');
  return function MockFormActionFooter({ submitLabel, onSubmit }: { submitLabel: string; onSubmit: () => void }) {
    return (
      <View>
        <Pressable accessibilityRole="button" onPress={onSubmit}>
          <Text>{submitLabel}</Text>
        </Pressable>
      </View>
    );
  };
});

jest.mock('heroui-native', () => {
  const React = require('react');
  const { Pressable, Text, View } = require('react-native');
  const Button = ({ children, onPress }: { children: React.ReactNode; onPress?: () => void }) => (
    <Pressable onPress={onPress}>
      <View>{children}</View>
    </Pressable>
  );
  Button.Label = ({ children }: { children: React.ReactNode }) => <Text>{children}</Text>;
  const Card = ({ children }: { children: React.ReactNode }) => <View>{children}</View>;
  Card.Body = ({ children }: { children: React.ReactNode }) => <View>{children}</View>;
  const Surface = ({ children }: { children: React.ReactNode }) => <View>{children}</View>;
  const Spinner = () => <View />;
  return { Button, Card, Spinner, Surface, Text };
});

import NewVolunteeringRoute from './new-volunteering';

describe('NewVolunteeringRoute', () => {
  beforeEach(() => {
    mockUseApi.mockReset().mockReturnValue({
      data: { data: [{ id: 7, name: 'Helping Hands', status: 'approved', member_role: 'owner' }] },
      isLoading: false,
      error: null,
    });
    mockCreateOpportunity.mockReset().mockResolvedValue({ data: { id: 19 } });
    mockReplace.mockClear();
    jest.spyOn(Alert, 'alert').mockImplementation(jest.fn());
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  it('requires the opportunity title to meet the React length limit', async () => {
    const { getByPlaceholderText, getByText } = render(<NewVolunteeringRoute />);

    fireEvent.press(getByText('Helping Hands'));
    fireEvent.changeText(getByPlaceholderText('What help do you need?'), 'Help');
    fireEvent.changeText(getByPlaceholderText('Describe the role, support, and expected impact.'), 'Help pack and deliver food parcels for local families.');
    fireEvent.press(getByText('Create opportunity'));

    await waitFor(() => {
      expect(Alert.alert).toHaveBeenCalledWith('Check opportunity details', 'Use at least 5 characters for the title.');
    });
    expect(mockCreateOpportunity).not.toHaveBeenCalled();
  });

  it('requires the opportunity description to meet the React length limit', async () => {
    const { getByPlaceholderText, getByText } = render(<NewVolunteeringRoute />);

    fireEvent.press(getByText('Helping Hands'));
    fireEvent.changeText(getByPlaceholderText('What help do you need?'), 'Food bank help');
    fireEvent.changeText(getByPlaceholderText('Describe the role, support, and expected impact.'), 'Too short.');
    fireEvent.press(getByText('Create opportunity'));

    await waitFor(() => {
      expect(Alert.alert).toHaveBeenCalledWith('Check opportunity details', 'Use at least 20 characters for the description.');
    });
    expect(mockCreateOpportunity).not.toHaveBeenCalled();
  });

  it('requires the end date to be after the start date', async () => {
    const { getAllByPlaceholderText, getByPlaceholderText, getByText } = render(<NewVolunteeringRoute />);

    fireEvent.press(getByText('Helping Hands'));
    fireEvent.changeText(getByPlaceholderText('What help do you need?'), 'Food bank help');
    fireEvent.changeText(getByPlaceholderText('Describe the role, support, and expected impact.'), 'Help pack and deliver food parcels for local families.');
    const [startDateInput, endDateInput] = getAllByPlaceholderText('YYYY-MM-DD');
    fireEvent.changeText(startDateInput, '2026-06-10');
    fireEvent.changeText(endDateInput, '2026-06-09');
    fireEvent.press(getByText('Create opportunity'));

    await waitFor(() => {
      expect(Alert.alert).toHaveBeenCalledWith('Check opportunity details', 'Use an end date after the start date.');
    });
    expect(mockCreateOpportunity).not.toHaveBeenCalled();
  });
});
