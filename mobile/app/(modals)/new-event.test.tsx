// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';
import { Alert } from 'react-native';

const mockCreateEvent = jest.fn().mockResolvedValue({ data: { id: 6 } });
const mockGetEvent = jest.fn();
const mockUpdateEvent = jest.fn().mockResolvedValue({ data: { id: 7 } });
const mockUploadEventImage = jest.fn();
const mockLaunchImageLibraryAsync = jest.fn();
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
        'create.eyebrow': 'New event',
        'create.title': 'Create Event',
        'create.editTitle': 'Edit Event',
        'create.subtitle': 'Add a gathering.',
        'create.titleLabel': 'Title',
        'create.titlePlaceholder': 'What is happening?',
        'create.descriptionLabel': 'Description',
        'create.descriptionPlaceholder': 'Tell members what to expect.',
        'create.coverImageLabel': 'Cover image',
        'create.coverImageHint': 'Add a photo.',
        'create.addImage': 'Add image',
        'create.replaceImage': 'Replace image',
        'create.removeImage': 'Remove',
        'create.imageTypeError': 'Choose a JPEG, PNG, WebP, or GIF image.',
        'create.imageSizeError': 'Choose an image under 5 MB.',
        'create.imagePickFailedTitle': 'Image not selected',
        'create.imagePickFailedDescription': 'We could not open your photo library.',
        'create.imageUploadFailedTitle': 'Event saved',
        'create.imageUploadFailedDescription': 'The event was saved, but the cover image could not be uploaded.',
        'create.categoryLabel': 'Category',
        'create.startLabel': 'Start',
        'create.endLabel': 'End',
        'create.datePlaceholder': 'YYYY-MM-DDTHH:mm',
        'create.optionalDatePlaceholder': 'Optional end time',
        'create.locationLabel': 'Location',
        'create.locationPlaceholder': 'Venue, town, or meeting place',
        'create.coordinatesLabel': 'Map coordinates',
        'create.coordinatesHint': 'Optional coordinates help maps and nearby event discovery place this event accurately.',
        'create.latitudeLabel': 'Latitude',
        'create.latitudePlaceholder': '51.5007',
        'create.longitudeLabel': 'Longitude',
        'create.longitudePlaceholder': '-0.1246',
        'create.remoteAttendance': 'Allow remote attendance',
        'create.videoUrlLabel': 'Video link',
        'create.videoUrlPlaceholder': 'https://...',
        'create.maxAttendeesLabel': 'Capacity',
        'create.maxAttendeesPlaceholder': 'Optional attendee limit',
        'create.federated': 'Share with federation',
        'create.reviewTitle': 'Ready to publish?',
        'create.reviewSubtitle': 'Review first.',
        'create.editReviewTitle': 'Ready to update?',
        'create.editReviewSubtitle': 'Save your changes.',
        'create.submit': 'Create event',
        'create.updateSubmit': 'Update event',
        'create.validationTitle': 'Check event details',
        'create.validationStartFuture': 'Choose a future start time.',
        'create.validationEndAfterStart': 'End time must be after the start time.',
        'create.validationCapacity': 'Capacity must be between 1 and 10,000.',
        'create.invalidCoordinates': 'Enter both latitude and longitude using valid coordinate ranges.',
        'create.loadFailed': 'Could not load event.',
        'category.workshop': 'Workshop',
        'category.social': 'Social',
        'category.outdoor': 'Outdoor',
        'category.online': 'Online',
        'category.meeting': 'Meeting',
        'category.training': 'Training',
        'category.other': 'Other',
        'common:back': 'Back',
      };
      return map[key] ?? key;
    },
    i18n: { language: 'en' },
  }),
}));

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#6366f1',
}));

jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    bg: '#ffffff',
    surface: '#f8f9fa',
    text: '#000000',
    textSecondary: '#666666',
    textMuted: '#999999',
    border: '#dddddd',
    error: '#e53e3e',
  }),
}));

jest.mock('@/lib/api/events', () => ({
  createEvent: (...args: unknown[]) => mockCreateEvent(...args),
  getEvent: (...args: unknown[]) => mockGetEvent(...args),
  getEventOnlineLink: (event: { online_link?: string | null; online_url?: string | null; video_url?: string | null }) =>
    event.online_link ?? event.online_url ?? event.video_url ?? null,
  updateEvent: (...args: unknown[]) => mockUpdateEvent(...args),
  uploadEventImage: (...args: unknown[]) => mockUploadEventImage(...args),
}));

jest.mock('@/lib/haptics', () => ({
  notificationAsync: jest.fn().mockResolvedValue(undefined),
  NotificationFeedbackType: { Success: 'success' },
}));

jest.mock('@expo/vector-icons', () => ({ Ionicons: 'View' }));
jest.mock('expo-image', () => ({ Image: 'View' }));
jest.mock('expo-image-picker', () => ({
  MediaTypeOptions: { Images: 'Images' },
  launchImageLibraryAsync: (...args: unknown[]) => mockLaunchImageLibraryAsync(...args),
}));
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
  const { Pressable, Text, TextInput, View } = require('react-native');
  const Button = ({ children, onPress }: { children: React.ReactNode; onPress?: () => void }) => (
    <Pressable onPress={onPress}>
      <View>{children}</View>
    </Pressable>
  );
  Button.Label = ({ children }: { children: React.ReactNode }) => <Text>{children}</Text>;
  const Card = ({ children }: { children: React.ReactNode }) => <View>{children}</View>;
  Card.Body = ({ children }: { children: React.ReactNode }) => <View>{children}</View>;
  return {
    Button,
    Card,
    Text,
    TextField: ({ children }: { children: React.ReactNode }) => <View>{children}</View>,
    Label: ({ children }: { children: React.ReactNode }) => <Text>{children}</Text>,
    Input: React.forwardRef((props: Record<string, unknown>, ref: React.Ref<unknown>) => <TextInput ref={ref} {...props} />),
    FieldError: ({ children }: { children: React.ReactNode }) => <Text>{children}</Text>,
  };
});

import NewEventRoute from './new-event';

describe('NewEventRoute', () => {
  let alertSpy: jest.SpyInstance;

  beforeEach(() => {
    alertSpy = jest.spyOn(Alert, 'alert').mockImplementation(() => undefined);
    mockSearchParams = {};
    mockCreateEvent.mockClear();
    mockGetEvent.mockReset();
    mockUpdateEvent.mockClear();
    mockUploadEventImage.mockReset().mockResolvedValue({ data: { image_url: '/uploads/events/cover.jpg' } });
    mockLaunchImageLibraryAsync.mockReset().mockResolvedValue({
      canceled: false,
      assets: [{ uri: 'file:///tmp/event-cover.jpg', mimeType: 'image/jpeg', fileSize: 1024 }],
    });
    mockReplace.mockClear();
  });

  afterEach(() => {
    alertSpy.mockRestore();
  });

  it('blocks event starts in the past', async () => {
    const { getByPlaceholderText, getByText } = render(<NewEventRoute />);

    fireEvent.changeText(getByPlaceholderText('What is happening?'), 'Repair workshop');
    fireEvent.changeText(getByPlaceholderText('Tell members what to expect.'), 'Bring something small to mend together.');
    fireEvent.changeText(getByPlaceholderText('YYYY-MM-DDTHH:mm'), '2000-01-01T09:00');
    fireEvent.press(getByText('Create event'));

    await waitFor(() => {
      expect(alertSpy).toHaveBeenCalledWith('Check event details', 'Choose a future start time.');
    });
    expect(mockCreateEvent).not.toHaveBeenCalled();
  });

  it('blocks end times before the start time', async () => {
    const { getByPlaceholderText, getByText } = render(<NewEventRoute />);

    fireEvent.changeText(getByPlaceholderText('What is happening?'), 'Repair workshop');
    fireEvent.changeText(getByPlaceholderText('Tell members what to expect.'), 'Bring something small to mend together.');
    fireEvent.changeText(getByPlaceholderText('YYYY-MM-DDTHH:mm'), '2099-01-02T12:00');
    fireEvent.changeText(getByPlaceholderText('Optional end time'), '2099-01-02T11:00');
    fireEvent.press(getByText('Create event'));

    await waitFor(() => {
      expect(alertSpy).toHaveBeenCalledWith('Check event details', 'End time must be after the start time.');
    });
    expect(mockCreateEvent).not.toHaveBeenCalled();
  });

  it('blocks capacity outside the supported range', async () => {
    const { getByPlaceholderText, getByText } = render(<NewEventRoute />);

    fireEvent.changeText(getByPlaceholderText('What is happening?'), 'Repair workshop');
    fireEvent.changeText(getByPlaceholderText('Tell members what to expect.'), 'Bring something small to mend together.');
    fireEvent.changeText(getByPlaceholderText('Optional attendee limit'), '0');
    fireEvent.press(getByText('Create event'));

    await waitFor(() => {
      expect(alertSpy).toHaveBeenCalledWith('Check event details', 'Capacity must be between 1 and 10,000.');
    });
    expect(mockCreateEvent).not.toHaveBeenCalled();
  });

  it('requires paired valid coordinates when coordinates are provided', async () => {
    const { getByPlaceholderText, getByText } = render(<NewEventRoute />);

    fireEvent.changeText(getByPlaceholderText('What is happening?'), 'Repair workshop');
    fireEvent.changeText(getByPlaceholderText('Tell members what to expect.'), 'Bring something small to mend together.');
    fireEvent.changeText(getByPlaceholderText('51.5007'), '91');
    fireEvent.changeText(getByPlaceholderText('-0.1246'), '-0.1246');
    fireEvent.press(getByText('Create event'));

    await waitFor(() => {
      expect(alertSpy).toHaveBeenCalledWith('Check event details', 'Enter both latitude and longitude using valid coordinate ranges.');
    });
    expect(mockCreateEvent).not.toHaveBeenCalled();
  });

  it('submits category and remote attendance fields using the event API contract', async () => {
    const { getByPlaceholderText, getByText } = render(<NewEventRoute />);

    fireEvent.changeText(getByPlaceholderText('What is happening?'), 'Repair workshop');
    fireEvent.changeText(getByPlaceholderText('Tell members what to expect.'), 'Bring something small to mend.');
    fireEvent.changeText(getByPlaceholderText('Venue, town, or meeting place'), 'Community hall');
    fireEvent.changeText(getByPlaceholderText('51.5007'), '51.501');
    fireEvent.changeText(getByPlaceholderText('-0.1246'), '-0.125');
    fireEvent.press(getByText('Workshop'));
    fireEvent.press(getByText('Allow remote attendance'));
    fireEvent.changeText(getByPlaceholderText('https://...'), 'https://meet.example/workshop');
    fireEvent.press(getByText('Create event'));

    await waitFor(() => {
      expect(mockCreateEvent).toHaveBeenCalledWith(expect.objectContaining({
        category_name: 'workshop',
        location: 'Community hall',
        latitude: 51.501,
        longitude: -0.125,
        is_online: true,
        online_link: 'https://meet.example/workshop',
      }));
    });
    expect(mockReplace).toHaveBeenCalledWith({ pathname: '/(modals)/event-detail', params: { id: '6' } });
  });

  it('uploads a selected cover image after creating the event', async () => {
    const { getByPlaceholderText, getByText } = render(<NewEventRoute />);

    fireEvent.changeText(getByPlaceholderText('What is happening?'), 'Repair workshop');
    fireEvent.changeText(getByPlaceholderText('Tell members what to expect.'), 'Bring something small to mend.');
    fireEvent.press(getByText('Add image'));
    await waitFor(() => expect(mockLaunchImageLibraryAsync).toHaveBeenCalled());
    fireEvent.press(getByText('Create event'));

    await waitFor(() => {
      expect(mockCreateEvent).toHaveBeenCalled();
    });
    expect(mockUploadEventImage).toHaveBeenCalledWith(6, 'file:///tmp/event-cover.jpg');
    expect(mockReplace).toHaveBeenCalledWith({ pathname: '/(modals)/event-detail', params: { id: '6' } });
  });

  it('hydrates an existing event and updates it in edit mode', async () => {
    mockSearchParams = { id: '7' };
    mockGetEvent.mockResolvedValueOnce({
      data: {
        id: 7,
        title: 'Existing workshop',
        description: 'Existing details for attendees.',
        start_date: '2099-01-02T12:00:00.000Z',
        end_date: '2099-01-02T13:00:00.000Z',
        location: 'Old hall',
        latitude: 51.5,
        longitude: -0.12,
        is_online: true,
        online_url: null,
        online_link: 'https://meet.example/old',
        max_attendees: 25,
        organizer: { id: 3, name: 'Jane Organizer', avatar: null },
        category: { id: 2, name: 'workshop', color: '#f59e0b' },
        rsvp_counts: { going: 0, interested: 0 },
        attendees_count: 0,
        spots_left: 25,
        is_full: false,
        status: 'published',
        federated_visibility: 'listed',
        user_rsvp: null,
        cover_image: null,
      },
    });

    const { getByDisplayValue, getByText } = render(<NewEventRoute />);

    await waitFor(() => expect(getByDisplayValue('Existing workshop')).toBeTruthy());
    fireEvent.changeText(getByDisplayValue('Existing workshop'), 'Updated workshop');
    fireEvent.press(getByText('Update event'));

    await waitFor(() => {
      expect(mockUpdateEvent).toHaveBeenCalledWith(7, expect.objectContaining({
        title: 'Updated workshop',
        description: 'Existing details for attendees.',
        location: 'Old hall',
        latitude: 51.5,
        longitude: -0.12,
        category_name: 'workshop',
        is_online: true,
        online_link: 'https://meet.example/old',
        max_attendees: 25,
        federated_visibility: 'listed',
      }));
    });
    expect(mockCreateEvent).not.toHaveBeenCalled();
    expect(mockReplace).toHaveBeenCalledWith({ pathname: '/(modals)/event-detail', params: { id: '7' } });
  });

  it('shows an existing cover image and uploads a replacement in edit mode', async () => {
    mockSearchParams = { id: '7' };
    mockGetEvent.mockResolvedValueOnce({
      data: {
        id: 7,
        title: 'Existing workshop',
        description: 'Existing details for attendees.',
        start_date: '2099-01-02T12:00:00.000Z',
        end_date: null,
        location: 'Old hall',
        is_online: false,
        online_url: null,
        max_attendees: null,
        organizer: { id: 3, name: 'Jane Organizer', avatar: null },
        category: { id: 2, name: 'workshop', color: '#f59e0b' },
        rsvp_counts: { going: 0, interested: 0 },
        attendees_count: 0,
        spots_left: 25,
        is_full: false,
        status: 'published',
        federated_visibility: 'none',
        user_rsvp: null,
        cover_image: '/uploads/events/existing.jpg',
      },
    });

    const { getByText } = render(<NewEventRoute />);

    await waitFor(() => expect(getByText('Replace image')).toBeTruthy());
    fireEvent.press(getByText('Replace image'));
    await waitFor(() => expect(mockLaunchImageLibraryAsync).toHaveBeenCalled());
    fireEvent.press(getByText('Update event'));

    await waitFor(() => expect(mockUpdateEvent).toHaveBeenCalled());
    expect(mockUploadEventImage).toHaveBeenCalledWith(7, 'file:///tmp/event-cover.jpg');
  });
});
