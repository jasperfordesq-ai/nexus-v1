// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';
import { Alert } from 'react-native';

const mockCreateJob = jest.fn().mockResolvedValue({ data: { id: 301 } });
const mockGetJobDetail = jest.fn();
const mockReplace = jest.fn();
let mockSearchParams: Record<string, string> = {};

jest.mock('expo-router', () => ({
  router: { replace: (...args: unknown[]) => mockReplace(...args), back: jest.fn() },
  useLocalSearchParams: () => mockSearchParams,
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => {
      const map: Record<string, string> = {
        'create.eyebrow': 'New role',
        'create.title': 'Create Job',
        'create.editTitle': 'Edit Job',
        'create.subtitle': 'Post a role.',
        'create.editSubtitle': 'Update the role details.',
        'create.titleLabel': 'Title',
        'create.titlePlaceholder': 'Role title',
        'create.descriptionLabel': 'Description',
        'create.descriptionPlaceholder': 'Describe the role, expectations, and next steps.',
        'create.typeLabel': 'Type',
        'create.commitmentLabel': 'Commitment',
        'create.locationLabel': 'Location',
        'create.locationPlaceholder': 'Where is the role based?',
        'create.categoryLabel': 'Category',
        'create.categoryPlaceholder': 'Optional category',
        'create.skillsLabel': 'Skills',
        'create.skillsPlaceholder': 'Comma-separated skills',
        'create.hoursLabel': 'Hours per week',
        'create.hoursPlaceholder': 'Optional hours',
        'create.creditsLabel': 'Time credits',
        'create.creditsPlaceholder': 'Optional credits',
        'create.deadlineLabel': 'Deadline',
        'create.deadlinePlaceholder': 'YYYY-MM-DD',
        'create.contactEmailLabel': 'Contact email',
        'create.contactEmailPlaceholder': 'name@example.org',
        'create.contactPhoneLabel': 'Contact phone',
        'create.contactPhonePlaceholder': '+1 555 123 4567',
        'create.salaryMinLabel': 'Salary minimum',
        'create.salaryMaxLabel': 'Salary maximum',
        'create.salaryPlaceholder': 'Optional amount',
        'create.salaryTypeLabel': 'Pay type',
        'create.salaryType.hourly': 'Hourly',
        'create.salaryType.monthly': 'Monthly',
        'create.salaryType.annual': 'Annual',
        'create.salaryCurrencyLabel': 'Currency',
        'create.salaryCurrencyPlaceholder': 'EUR',
        'create.salaryNegotiable': 'Salary negotiable',
        'create.blindHiring': 'Enable blind hiring',
        'create.taglineLabel': 'Company tagline',
        'create.taglinePlaceholder': 'What makes this team a good place to work?',
        'create.videoUrlLabel': 'Culture video URL',
        'create.videoUrlPlaceholder': 'https://example.org/video',
        'create.companySizeLabel': 'Company size',
        'create.companySize.1-10': '1-10',
        'create.companySize.11-50': '11-50',
        'create.companySize.51-200': '51-200',
        'create.companySize.201-500': '201-500',
        'create.companySize.500+': '500+',
        'create.benefitsLabel': 'Benefits and perks',
        'create.benefitsPlaceholder': 'Comma-separated benefits',
        'create.remote': 'Remote role',
        'create.reviewTitle': 'Ready to publish?',
        'create.reviewSubtitle': 'Review first.',
        'create.editReviewTitle': 'Ready to update?',
        'create.editReviewSubtitle': 'Save your changes.',
        'create.submit': 'Create job',
        'create.updateSubmit': 'Update job',
        'create.validationTitle': 'Check job details',
        'create.deadlinePast': 'Deadline must be a future date.',
        'create.salaryRangeInvalid': 'Minimum salary cannot exceed maximum salary.',
        'create.salaryRequired': 'Salary range required. You may mark salary negotiable to omit it.',
        'create.loadFailed': 'Could not load job.',
        'create.failedTitle': 'Job not created',
        'create.failedDescription': 'We could not create the job.',
        'create.editFailedTitle': 'Job not updated',
        'create.editFailedDescription': 'We could not update the job.',
        'filters.type.paid': 'Paid',
        'filters.type.volunteer': 'Volunteer',
        'filters.type.timebank': 'Timebank',
        'filters.commitment.flexible': 'Flexible',
        'filters.commitment.part_time': 'Part Time',
        'filters.commitment.full_time': 'Full Time',
        'filters.commitment.one_off': 'One-off',
        'common:back': 'Back',
      };
      return map[key] ?? key;
    },
    i18n: { language: 'en' },
  }),
}));

jest.mock('@/lib/hooks/useTenant', () => ({ usePrimaryColor: () => '#6366f1' }));
jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    bg: '#ffffff',
    text: '#000000',
    textSecondary: '#666666',
    textMuted: '#999999',
    border: '#dddddd',
  }),
}));
jest.mock('@/lib/api/jobs', () => ({
  createJob: (...args: unknown[]) => mockCreateJob(...args),
  getJobDetail: (...args: unknown[]) => mockGetJobDetail(...args),
  updateJob: jest.fn().mockResolvedValue({ data: { id: 301 } }),
}));
jest.mock('@/lib/haptics', () => ({
  notificationAsync: jest.fn().mockResolvedValue(undefined),
  NotificationFeedbackType: { Success: 'success' },
}));
jest.mock('@expo/vector-icons', () => ({ Ionicons: 'View' }));
jest.mock('@/components/ui/AppTopBar', () => 'View');
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
  return { Button, Card, Text };
});

import NewJobRoute from './new-job';
import { updateJob } from '@/lib/api/jobs';

describe('NewJobRoute', () => {
  let alertSpy: jest.SpyInstance;

  beforeEach(() => {
    alertSpy = jest.spyOn(Alert, 'alert').mockImplementation(() => undefined);
    mockSearchParams = {};
    mockCreateJob.mockClear();
    mockGetJobDetail.mockReset();
    (updateJob as jest.Mock).mockClear();
    mockReplace.mockClear();
  });

  afterEach(() => {
    alertSpy.mockRestore();
  });

  it('blocks paid roles without salary transparency unless negotiable', async () => {
    const { getByPlaceholderText, getByText } = render(<NewJobRoute />);

    fireEvent.changeText(getByPlaceholderText('Role title'), 'Community coordinator');
    fireEvent.changeText(getByPlaceholderText('Describe the role, expectations, and next steps.'), 'Coordinate local sessions and support volunteers.');
    fireEvent.press(getByText('Paid'));
    fireEvent.press(getByText('Create job'));

    await waitFor(() => {
      expect(alertSpy).toHaveBeenCalledWith('Check job details', 'Salary range required. You may mark salary negotiable to omit it.');
    });
    expect(mockCreateJob).not.toHaveBeenCalled();
  });

  it('allows paid roles without salary values when salary is negotiable', async () => {
    const { getByPlaceholderText, getByText } = render(<NewJobRoute />);

    fireEvent.changeText(getByPlaceholderText('Role title'), 'Community coordinator');
    fireEvent.changeText(getByPlaceholderText('Describe the role, expectations, and next steps.'), 'Coordinate local sessions and support volunteers.');
    fireEvent.press(getByText('Paid'));
    fireEvent.press(getByText('Salary negotiable'));
    fireEvent.press(getByText('Create job'));

    await waitFor(() => {
      expect(mockCreateJob).toHaveBeenCalledWith(expect.objectContaining({
        type: 'paid',
        salary_min: null,
        salary_max: null,
        salary_negotiable: true,
      }));
    });
  });

  it('blocks salary ranges where minimum exceeds maximum', async () => {
    const { getAllByPlaceholderText, getByPlaceholderText, getByText } = render(<NewJobRoute />);

    fireEvent.changeText(getByPlaceholderText('Role title'), 'Community coordinator');
    fireEvent.changeText(getByPlaceholderText('Describe the role, expectations, and next steps.'), 'Coordinate local sessions and support volunteers.');
    fireEvent.press(getByText('Paid'));
    const salaryInputs = getAllByPlaceholderText('Optional amount');
    fireEvent.changeText(salaryInputs[0], '55000');
    fireEvent.changeText(salaryInputs[1], '42000');
    fireEvent.press(getByText('Create job'));

    await waitFor(() => {
      expect(alertSpy).toHaveBeenCalledWith('Check job details', 'Minimum salary cannot exceed maximum salary.');
    });
    expect(mockCreateJob).not.toHaveBeenCalled();
  });

  it('blocks deadlines in the past', async () => {
    const { getByPlaceholderText, getByText } = render(<NewJobRoute />);

    fireEvent.changeText(getByPlaceholderText('Role title'), 'Community coordinator');
    fireEvent.changeText(getByPlaceholderText('Describe the role, expectations, and next steps.'), 'Coordinate local sessions and support volunteers.');
    fireEvent.changeText(getByPlaceholderText('YYYY-MM-DD'), '2000-01-01');
    fireEvent.press(getByText('Create job'));

    await waitFor(() => {
      expect(alertSpy).toHaveBeenCalledWith('Check job details', 'Deadline must be a future date.');
    });
    expect(mockCreateJob).not.toHaveBeenCalled();
  });

  it('submits contact, salary transparency, hiring, and branding fields for paid roles', async () => {
    const { getAllByPlaceholderText, getByPlaceholderText, getByText } = render(<NewJobRoute />);

    fireEvent.changeText(getByPlaceholderText('Role title'), 'Community coordinator');
    fireEvent.changeText(getByPlaceholderText('Describe the role, expectations, and next steps.'), 'Coordinate local sessions and support volunteers.');
    fireEvent.press(getByText('Paid'));
    fireEvent.changeText(getByPlaceholderText('name@example.org'), 'jobs@example.org');
    fireEvent.changeText(getByPlaceholderText('+1 555 123 4567'), '+353 1 234 5678');
    const salaryInputs = getAllByPlaceholderText('Optional amount');
    fireEvent.changeText(salaryInputs[0], '30000');
    fireEvent.changeText(salaryInputs[1], '36000');
    fireEvent.changeText(getByPlaceholderText('EUR'), 'EUR');
    fireEvent.press(getByText('Monthly'));
    fireEvent.press(getByText('Enable blind hiring'));
    fireEvent.changeText(getByPlaceholderText('What makes this team a good place to work?'), 'Community-first team');
    fireEvent.changeText(getByPlaceholderText('https://example.org/video'), 'https://example.org/culture');
    fireEvent.press(getByText('11-50'));
    fireEvent.changeText(getByPlaceholderText('Comma-separated benefits'), 'Mentoring, Flexible hours');
    fireEvent.press(getByText('Create job'));

    await waitFor(() => {
      expect(mockCreateJob).toHaveBeenCalledWith(expect.objectContaining({
        type: 'paid',
        contact_email: 'jobs@example.org',
        contact_phone: '+353 1 234 5678',
        salary_min: 30000,
        salary_max: 36000,
        salary_currency: 'EUR',
        salary_type: 'monthly',
        salary_negotiable: false,
        blind_hiring: true,
        tagline: 'Community-first team',
        video_url: 'https://example.org/culture',
        company_size: '11-50',
        benefits: ['Mentoring', 'Flexible hours'],
      }));
    });
    expect(mockReplace).toHaveBeenCalledWith({ pathname: '/(modals)/job-detail', params: { id: '301' } });
  });

  it('hydrates an existing job and updates it in edit mode', async () => {
    mockSearchParams = { id: '301' };
    mockGetJobDetail.mockResolvedValueOnce({
      data: {
        id: 301,
        title: 'Existing role',
        description: 'Existing description',
        type: 'timebank',
        commitment: 'part_time',
        location: 'Cork',
        is_remote: false,
        category: 'Community',
        skills_required: ['Planning', 'Support'],
        hours_per_week: 8,
        time_credits: 4,
        contact_email: 'old@example.org',
        contact_phone: '+1 555 123 4567',
        salary_min: null,
        salary_max: null,
        salary_currency: null,
        salary_type: null,
        salary_negotiable: false,
        blind_hiring: false,
        tagline: 'Helpful team',
        video_url: '',
        company_size: '51-200',
        benefits: ['Mentoring'],
        deadline: '2026-07-01T00:00:00Z',
      },
    });

    const { getByDisplayValue, getByText } = render(<NewJobRoute />);

    await waitFor(() => expect(getByDisplayValue('Existing role')).toBeTruthy());
    expect(getByText('Edit Job')).toBeTruthy();
    expect(getByText('Update the role details.')).toBeTruthy();
    fireEvent.changeText(getByDisplayValue('Existing role'), 'Updated role');
    fireEvent.press(getByText('Update job'));

    await waitFor(() => {
      expect(updateJob).toHaveBeenCalledWith(301, expect.objectContaining({
        title: 'Updated role',
        description: 'Existing description',
        type: 'timebank',
        commitment: 'part_time',
        company_size: '51-200',
        skills_required: ['Planning', 'Support'],
      }));
    });
  });
});
