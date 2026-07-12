// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';

const mockGetTemplates = jest.fn();
const mockPreview = jest.fn();
const mockMaterialize = jest.fn();
const mockReplace = jest.fn();
const mockShowToast = jest.fn();

jest.mock('expo-router', () => ({
  router: {
    canGoBack: () => true,
    back: jest.fn(),
    replace: (...args: unknown[]) => mockReplace(...args),
  },
}));
jest.mock('@/components/ui/AppTopBar', () => {
  const React = require('react');
  const { Text } = require('react-native');
  return ({ title }: { title: string }) => <Text>{title}</Text>;
});
jest.mock('@/components/ModalErrorBoundary', () => ({ children }: { children: React.ReactNode }) => children);
jest.mock('@/components/ui/AppToast', () => ({
  useAppToast: () => ({ show: mockShowToast }),
}));
jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({ text: '#111111', textMuted: '#777777' }),
}));
jest.mock('react-i18next', () => {
  const labels: Record<string, string> = {
    'templates.mobile.safetyTitle': 'Templates copy configuration only',
    'templates.mobile.useTemplate': 'Use template',
    'templates.mobile.scheduleTitle': 'Schedule draft',
    'templates.mobile.eventTitle': 'Event title',
    'templates.mobile.start': 'Start',
    'templates.mobile.end': 'End',
    'templates.mobile.review': 'Review draft',
    'templates.mobile.readyTitle': 'Ready to create a draft',
    'templates.mobile.createDraft': 'Create draft',
  };
  return {
    useTranslation: () => ({
      t: (key: string, values?: Record<string, unknown>) => {
        let value = labels[key] ?? key;
        Object.entries(values ?? {}).forEach(([name, replacement]) => {
          value = value.replace(`{{${name}}}`, String(replacement));
        });
        return value;
      },
    }),
  };
});
jest.mock('@/lib/api/eventTemplates', () => ({
  getEventTemplates: (...args: unknown[]) => mockGetTemplates(...args),
  previewEventTemplate: (...args: unknown[]) => mockPreview(...args),
  materializeEventTemplate: (...args: unknown[]) => mockMaterialize(...args),
}));

import EventTemplatesScreen from './event-templates';

const template = {
  id: 4,
  status: 'active',
  current_version: 1,
  source_event: { id: 9, title: 'Source event' },
  version: {
    number: 1,
    configuration: {
      title: 'Reusable event',
      description: 'Reusable description',
      location: 'Community hall',
      max_attendees: 30,
      timezone: 'UTC',
      all_day: false,
      is_online: false,
      allow_remote_attendance: false,
    },
    snapshot: { immutable: true },
    copied_fields: ['title'],
    skipped_fields: ['related.registrations'],
  },
  usage: { materialization_count: 2 },
  capabilities: { materialize: true },
};

beforeEach(() => {
  jest.clearAllMocks();
  mockGetTemplates.mockResolvedValue({
    data: [template],
    meta: { per_page: 20, next_cursor: null, has_more: false },
  });
  mockPreview.mockResolvedValue({
    kind: 'materialization',
    template_id: 4,
    template_version: 1,
    configuration: { ...template.version.configuration, title: 'Fresh mobile draft' },
    schedule: {
      start_at: '2030-08-01T10:00:00Z',
      end_at: '2030-08-01T12:00:00Z',
      timezone: 'UTC',
      all_day: false,
    },
    override_fields: ['title'],
    checklist: [{ code: 'event_template_check_federation_none', passed: true }],
    will_create: {
      publication_status: 'draft',
      publish: false,
      register: false,
      notify: false,
      federate: false,
    },
  });
  mockMaterialize.mockResolvedValue({
    created_event: {
      id: 21,
      title: 'Fresh mobile draft',
      publication_status: 'draft',
      operational_status: 'scheduled',
    },
    workflow: {
      fresh_draft: true,
      published: false,
      registrations_copied: false,
      notifications_sent: false,
      federated: false,
    },
    changed: true,
    idempotent_replay: false,
  });
});

describe('EventTemplatesScreen', () => {
  it('keeps mobile bounded to selecting a template and creating a reviewed draft', async () => {
    const screen = render(<EventTemplatesScreen />);

    expect(await screen.findByText('Reusable event')).toBeTruthy();
    expect(screen.getByText('Templates copy configuration only')).toBeTruthy();
    fireEvent.press(screen.getByText('Use template'));

    fireEvent.changeText(screen.getByDisplayValue('Reusable event'), 'Fresh mobile draft');
    fireEvent.changeText(screen.getByTestId('event-template-start'), '2030-08-01T10:00');
    fireEvent.changeText(screen.getByTestId('event-template-end'), '2030-08-01T12:00');
    fireEvent.press(screen.getByText('Review draft'));

    expect(await screen.findByText('Ready to create a draft')).toBeTruthy();
    expect(mockMaterialize).not.toHaveBeenCalled();
    fireEvent.press(screen.getByText('Create draft'));

    await waitFor(() => {
      expect(mockMaterialize).toHaveBeenCalledWith(
        4,
        expect.objectContaining({
          template_version: 1,
          start_time: '2030-08-01T10:00',
          end_time: '2030-08-01T12:00',
          overrides: { title: 'Fresh mobile draft' },
        }),
        expect.any(String),
      );
      expect(mockReplace).toHaveBeenCalledWith({
        pathname: '/(modals)/edit-event',
        params: { id: '21' },
      });
    });
  });
});
