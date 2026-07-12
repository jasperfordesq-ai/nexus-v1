// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';

const mockCreateEvent = jest.fn().mockResolvedValue({ data: { id: 6 } });
const mockGetEvent = jest.fn();
const mockGetEventCategories = jest.fn();
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
        'create.timezoneLabel': 'Event time zone',
        'create.timezonePlaceholder': 'Europe/Dublin',
        'create.timezoneHint': 'Use an IANA time zone.',
        'create.allDay': 'All-day event',
        'create.allDayEndLabel': 'Final event day',
        'create.dateOnlyPlaceholder': 'YYYY-MM-DD',
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
  getEventCategories: (...args: unknown[]) => mockGetEventCategories(...args),
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

jest.mock('@/components/ui/AppToast', () => {
  // Stable references so screens that put `show` in a useCallback/useEffect
  // dependency array don't re-run their effects on every render.
  const show = jest.fn();
  const hide = jest.fn();
  return { useAppToast: () => ({ show, hide, isToastVisible: false }) };
});

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
  const TagGroupContext = React.createContext(null);
  const TagGroup = ({ children, onSelectionChange }: { children: React.ReactNode; onSelectionChange?: (keys: Set<string | number>) => void }) => (
    <TagGroupContext.Provider value={{ onSelectionChange }}>
      <View>{children}</View>
    </TagGroupContext.Provider>
  );
  TagGroup.List = ({ children }: { children: React.ReactNode }) => <View>{children}</View>;
  TagGroup.Item = ({ children, id }: { children: React.ReactNode; id: string | number }) => {
    const ctx = React.useContext(TagGroupContext);
    return (
      <Pressable onPress={() => ctx?.onSelectionChange?.(new Set([id]))}>
        <View>{children}</View>
      </Pressable>
    );
  };
  TagGroup.ItemLabel = ({ children }: { children: React.ReactNode }) => <Text>{children}</Text>;
  return {
    Button,
    Card,
    Text,
    TextField: ({ children }: { children: React.ReactNode }) => <View>{children}</View>,
    Label: ({ children }: { children: React.ReactNode }) => <Text>{children}</Text>,
    Input: React.forwardRef((props: Record<string, unknown>, ref: React.Ref<unknown>) => <TextInput ref={ref} {...props} />),
    FieldError: ({ children }: { children: React.ReactNode }) => <Text>{children}</Text>,
    TagGroup,
  };
});

import NewEventRoute from './new-event';
import { useAppToast } from '@/components/ui/AppToast';

const showToast = useAppToast().show as jest.Mock;
const sharedEvent = require('../../../contracts/events/v2/event-detail.json');
const canonicalEditEvent = {
  ...sharedEvent,
  id: 7,
  title: 'Existing workshop',
  description: 'Existing details for attendees.',
  schedule: {
    ...sharedEvent.schedule,
    start_at: '2099-01-02T12:00:00.000Z',
    end_at: '2099-01-02T13:00:00.000Z',
  },
  location: {
    ...sharedEvent.location,
    label: 'Old hall',
    latitude: 51.5,
    longitude: -0.12,
    mode: 'hybrid',
  },
  online_access: {
    ...sharedEvent.online_access,
    mode: 'hybrid',
    reveal_state: 'available',
    join_url: 'https://meet.example/old',
  },
  relationship: {
    ...sharedEvent.relationship,
    capacity: { ...sharedEvent.relationship.capacity, limit: 25 },
  },
  category: { id: 4, name: 'Workshop', slug: 'workshop', colour: '#f59e0b' },
  permissions: { ...sharedEvent.permissions, edit: true },
  federated_visibility: 'listed',
};

describe('NewEventRoute', () => {
  beforeEach(() => {
    showToast.mockClear();
    mockSearchParams = {};
    mockCreateEvent.mockClear();
    mockGetEvent.mockReset();
    mockGetEventCategories.mockReset().mockReturnValue(new Promise(() => undefined));
    mockUpdateEvent.mockClear();
    mockUploadEventImage.mockReset().mockResolvedValue({ data: { image_url: '/uploads/events/cover.jpg' } });
    mockLaunchImageLibraryAsync.mockReset().mockResolvedValue({
      canceled: false,
      assets: [{ uri: 'file:///tmp/event-cover.jpg', mimeType: 'image/jpeg', fileSize: 1024 }],
    });
    mockReplace.mockClear();
  });

  it('keeps the native create event frame full height with an explicit background', () => {
    const { getByTestId } = render(<NewEventRoute />);
    const screen = getByTestId('new-event-screen');
    const scroll = getByTestId('new-event-scroll');

    expect(screen.props.style).toEqual(expect.objectContaining({
      flex: 1,
      backgroundColor: '#ffffff',
    }));
    expect(scroll.props.style).toEqual(expect.objectContaining({
      flex: 1,
      backgroundColor: '#ffffff',
    }));
    expect(scroll.props.contentContainerStyle).toEqual(expect.objectContaining({
      flexGrow: 1,
      backgroundColor: '#ffffff',
      paddingBottom: 120,
    }));
  });

  it('blocks event starts in the past', async () => {
    const { getByPlaceholderText, getByText } = render(<NewEventRoute />);

    fireEvent.changeText(getByPlaceholderText('What is happening?'), 'Repair workshop');
    fireEvent.changeText(getByPlaceholderText('Tell members what to expect.'), 'Bring something small to mend together.');
    fireEvent.changeText(getByPlaceholderText('YYYY-MM-DDTHH:mm'), '2000-01-01T09:00');
    fireEvent.press(getByText('Create event'));

    await waitFor(() => {
      expect(showToast).toHaveBeenCalledWith({ title: 'Check event details', description: 'Choose a future start time.', variant: 'warning' });
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
      expect(showToast).toHaveBeenCalledWith({ title: 'Check event details', description: 'End time must be after the start time.', variant: 'warning' });
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
      expect(showToast).toHaveBeenCalledWith({ title: 'Check event details', description: 'Capacity must be between 1 and 10,000.', variant: 'warning' });
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
      expect(showToast).toHaveBeenCalledWith({ title: 'Check event details', description: 'Enter both latitude and longitude using valid coordinate ranges.', variant: 'warning' });
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
        category_id: null,
        category_name: 'workshop',
        location: 'Community hall',
        latitude: 51.501,
        longitude: -0.125,
        is_online: true,
        video_url: 'https://meet.example/workshop',
        timezone: expect.any(String),
        all_day: false,
      }));
    });
    await waitFor(() => expect(mockReplace).toHaveBeenCalledWith({ pathname: '/(modals)/event-detail', params: { id: '6' } }));
  });

  it('converts an inclusive all-day range in the selected IANA timezone', async () => {
    const { getAllByPlaceholderText, getByPlaceholderText, getByText } = render(<NewEventRoute />);

    fireEvent.changeText(getByPlaceholderText('What is happening?'), 'Brisbane community weekend');
    fireEvent.changeText(getByPlaceholderText('Tell members what to expect.'), 'A two-day community programme.');
    fireEvent.changeText(getByPlaceholderText('Europe/Dublin'), 'Australia/Brisbane');
    fireEvent.press(getByText('All-day event'));
    const dateFields = getAllByPlaceholderText('YYYY-MM-DD');
    fireEvent.changeText(dateFields[0], '2099-01-02');
    fireEvent.changeText(dateFields[1], '2099-01-03');
    fireEvent.press(getByText('Create event'));

    await waitFor(() => {
      expect(mockCreateEvent).toHaveBeenCalledWith(expect.objectContaining({
        start_time: '2099-01-01T14:00:00.000Z',
        end_time: '2099-01-03T14:00:00.000Z',
        timezone: 'Australia/Brisbane',
        all_day: true,
      }));
    });
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
    await waitFor(() => expect(mockReplace).toHaveBeenCalledWith({ pathname: '/(modals)/event-detail', params: { id: '6' } }));
  });

  it('hydrates an existing event and updates it in edit mode', async () => {
    mockSearchParams = { id: '7' };
    mockGetEvent.mockResolvedValueOnce({ data: canonicalEditEvent });

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
        category_id: 4,
        category_name: null,
        series_id: 12,
        is_online: true,
        video_url: 'https://meet.example/old',
        max_attendees: 25,
        federated_visibility: 'listed',
        timezone: canonicalEditEvent.schedule.timezone,
        all_day: canonicalEditEvent.schedule.all_day,
      }));
    });
    expect(mockCreateEvent).not.toHaveBeenCalled();
    await waitFor(() => expect(mockReplace).toHaveBeenCalledWith({ pathname: '/(modals)/event-detail', params: { id: '7' } }));
  });

  it('does not submit a deep-linked edit when the server denies edit permission', async () => {
    mockSearchParams = { id: '7' };
    mockGetEvent.mockResolvedValueOnce({
      data: {
        ...canonicalEditEvent,
        permissions: { ...canonicalEditEvent.permissions, edit: false },
      },
    });

    const { getByText } = render(<NewEventRoute />);

    await waitFor(() => expect(showToast).toHaveBeenCalledWith(expect.objectContaining({
      description: 'Could not load event.',
      variant: 'danger',
    })));
    fireEvent.press(getByText('Update event'));

    expect(mockUpdateEvent).not.toHaveBeenCalled();
  });

  it('does not silently disable federation when v2 omits legacy visibility state', async () => {
    mockSearchParams = { id: '7' };
    const eventWithoutFederation: Partial<typeof canonicalEditEvent> = { ...canonicalEditEvent };
    delete eventWithoutFederation.federated_visibility;
    mockGetEvent.mockResolvedValueOnce({ data: eventWithoutFederation });

    const { getByDisplayValue, getByText } = render(<NewEventRoute />);

    await waitFor(() => expect(getByDisplayValue('Existing workshop')).toBeTruthy());
    fireEvent.press(getByText('Update event'));

    await waitFor(() => expect(mockUpdateEvent).toHaveBeenCalled());
    expect(mockUpdateEvent.mock.calls[0][1]).not.toEqual(expect.objectContaining({
      federated_visibility: 'none',
    }));
  });

  it('shows an existing cover image and uploads a replacement in edit mode', async () => {
    mockSearchParams = { id: '7' };
    mockGetEvent.mockResolvedValueOnce({
      data: {
        ...canonicalEditEvent,
        schedule: { ...canonicalEditEvent.schedule, end_at: null },
        location: { ...canonicalEditEvent.location, mode: 'in_person' },
        online_access: {
          ...canonicalEditEvent.online_access,
          mode: 'in_person',
          reveal_state: 'not_applicable',
          join_url: null,
          video_url: null,
        },
        relationship: {
          ...canonicalEditEvent.relationship,
          capacity: { ...canonicalEditEvent.relationship.capacity, limit: null },
        },
        federated_visibility: 'none',
        primary_image: { url: '/uploads/events/existing.jpg', alt_text: 'Existing workshop' },
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
