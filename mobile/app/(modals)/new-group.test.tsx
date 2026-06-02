// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { Alert } from 'react-native';
import { fireEvent, render, waitFor } from '@testing-library/react-native';

const mockCreateGroup = jest.fn().mockResolvedValue({ data: { id: 484 } });
const mockGetGroup = jest.fn();
const mockGetGroupTemplates = jest.fn();
const mockUpdateGroup = jest.fn();
const mockUploadGroupImage = jest.fn();
const mockLaunchImageLibraryAsync = jest.fn();
const mockReplace = jest.fn();
let mockSearchParams: Record<string, string | undefined> = {};

jest.mock('expo-router', () => ({
  router: { replace: (...args: unknown[]) => mockReplace(...args), back: jest.fn() },
  useLocalSearchParams: () => mockSearchParams,
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => {
      const map: Record<string, string> = {
        'create.eyebrow': 'New group',
        'create.title': 'Create Group',
        'create.editTitle': 'Edit Group',
        'create.subtitle': 'Start a community space.',
        'create.nameLabel': 'Group name',
        'create.namePlaceholder': 'Name your group',
        'create.descriptionLabel': 'Description',
        'create.descriptionPlaceholder': 'What is this group for?',
        'create.imageLabel': 'Group image',
        'create.imageHint': 'Add a recognisable image for this group.',
        'create.addImage': 'Add image',
        'create.replaceImage': 'Replace image',
        'create.removeImage': 'Remove',
        'create.imageTypeError': 'Choose a JPEG, PNG, WebP, or GIF image.',
        'create.imageSizeError': 'Choose an image under 5 MB.',
        'create.imagePickFailedTitle': 'Image not selected',
        'create.imagePickFailedDescription': 'We could not open your photo library.',
        'create.imageUploadFailedTitle': 'Group saved',
        'create.imageUploadFailedDescription': 'The group was saved, but the image could not be uploaded.',
        'create.locationLabel': 'Location',
        'create.locationPlaceholder': 'Optional place or area',
        'create.coordinatesLabel': 'Map coordinates',
        'create.coordinatesHint': 'Optional coordinates help maps and nearby group discovery place this group accurately.',
        'create.latitudeLabel': 'Latitude',
        'create.latitudePlaceholder': '51.5007',
        'create.longitudeLabel': 'Longitude',
        'create.longitudePlaceholder': '-0.1246',
        'create.templateLabel': 'Group template',
        'create.visibilityLabel': 'Visibility',
        'create.federated': 'List in federation',
        'create.reviewTitle': 'Ready to publish?',
        'create.editReviewTitle': 'Ready to save?',
        'create.reviewSubtitle': 'Check first.',
        'create.submit': 'Create group',
        'create.updateSubmit': 'Update group',
        'create.validationTitle': 'Check group details',
        'create.validationRequired': 'Add a group name and description before continuing.',
        'create.validationNameLength': 'Use 3 to 100 characters for the group name.',
        'create.validationDescriptionLength': 'Use 20 to 2000 characters for the group description.',
        'create.invalidCoordinates': 'Enter both latitude and longitude using valid coordinate ranges.',
        public: 'Public',
        private: 'Private',
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
    error: '#dc2626',
    surface: '#f8fafc',
  }),
}));
jest.mock('@/lib/api/groups', () => ({
  createGroup: (...args: unknown[]) => mockCreateGroup(...args),
  getGroup: (...args: unknown[]) => mockGetGroup(...args),
  getGroupTemplates: (...args: unknown[]) => mockGetGroupTemplates(...args),
  updateGroup: (...args: unknown[]) => mockUpdateGroup(...args),
  uploadGroupImage: (...args: unknown[]) => mockUploadGroupImage(...args),
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

import NewGroupRoute from './new-group';

describe('NewGroupRoute', () => {
  beforeEach(() => {
    mockCreateGroup.mockClear();
    mockGetGroup.mockReset();
    mockGetGroupTemplates.mockReset().mockResolvedValue({ data: [] });
    mockUpdateGroup.mockReset();
    mockUpdateGroup.mockResolvedValue({ data: { id: 484 } });
    mockUploadGroupImage.mockReset().mockResolvedValue({ data: { image_url: '/uploads/groups/group.jpg' } });
    mockLaunchImageLibraryAsync.mockReset().mockResolvedValue({
      canceled: false,
      assets: [{ uri: 'file:///tmp/group.jpg', mimeType: 'image/jpeg', fileSize: 1024 }],
    });
    mockReplace.mockClear();
    mockSearchParams = {};
    jest.spyOn(Alert, 'alert').mockImplementation(jest.fn());
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  it('keeps the native create group frame full height with an explicit background', () => {
    const { getByTestId } = render(<NewGroupRoute />);
    const screen = getByTestId('new-group-screen');
    const scroll = getByTestId('new-group-scroll');

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

  it('requires a description before creating a group', async () => {
    const { getByPlaceholderText, getByText } = render(<NewGroupRoute />);

    fireEvent.changeText(getByPlaceholderText('Name your group'), 'Repair club');
    fireEvent.press(getByText('Create group'));

    await waitFor(() => {
      expect(Alert.alert).toHaveBeenCalledWith('Check group details', 'Add a group name and description before continuing.');
    });
    expect(mockCreateGroup).not.toHaveBeenCalled();
  });

  it('requires the group name to meet the frontend length limits', async () => {
    const { getByPlaceholderText, getByText } = render(<NewGroupRoute />);

    fireEvent.changeText(getByPlaceholderText('Name your group'), 'Go');
    fireEvent.changeText(getByPlaceholderText('What is this group for?'), 'A group for sharing repair skills and local mending sessions.');
    fireEvent.press(getByText('Create group'));

    await waitFor(() => {
      expect(Alert.alert).toHaveBeenCalledWith('Check group details', 'Use 3 to 100 characters for the group name.');
    });
    expect(mockCreateGroup).not.toHaveBeenCalled();
  });

  it('requires the group description to meet the frontend length limits', async () => {
    const { getByPlaceholderText, getByText } = render(<NewGroupRoute />);

    fireEvent.changeText(getByPlaceholderText('Name your group'), 'Repair club');
    fireEvent.changeText(getByPlaceholderText('What is this group for?'), 'Too short.');
    fireEvent.press(getByText('Create group'));

    await waitFor(() => {
      expect(Alert.alert).toHaveBeenCalledWith('Check group details', 'Use 20 to 2000 characters for the group description.');
    });
    expect(mockCreateGroup).not.toHaveBeenCalled();
  });

  it('submits group visibility and federation settings', async () => {
    const { getByPlaceholderText, getByText } = render(<NewGroupRoute />);

    fireEvent.changeText(getByPlaceholderText('Name your group'), 'Repair club');
    fireEvent.changeText(getByPlaceholderText('What is this group for?'), 'A group for sharing repair skills and local mending sessions.');
    fireEvent.changeText(getByPlaceholderText('Optional place or area'), 'Community hall');
    fireEvent.changeText(getByPlaceholderText('51.5007'), '51.501');
    fireEvent.changeText(getByPlaceholderText('-0.1246'), '-0.125');
    fireEvent.press(getByText('Private'));
    fireEvent.press(getByText('List in federation'));
    fireEvent.press(getByText('Create group'));

    await waitFor(() => {
      expect(mockCreateGroup).toHaveBeenCalledWith(expect.objectContaining({
        name: 'Repair club',
        description: 'A group for sharing repair skills and local mending sessions.',
        visibility: 'private',
        location: 'Community hall',
        latitude: 51.501,
        longitude: -0.125,
        federated_visibility: 'listed',
      }));
    });
    expect(mockReplace).toHaveBeenCalledWith({ pathname: '/(modals)/group-detail', params: { id: '484' } });
  });

  it('loads group templates for new groups and applies default visibility', async () => {
    mockGetGroupTemplates.mockResolvedValueOnce({
      data: [
        { id: 12, name: 'Private circle', icon: 'lock', default_visibility: 'private' },
      ],
    });

    const { getByPlaceholderText, getByText } = render(<NewGroupRoute />);

    await waitFor(() => expect(getByText('Private circle')).toBeTruthy());
    fireEvent.press(getByText('Private circle'));
    fireEvent.changeText(getByPlaceholderText('Name your group'), 'Repair club');
    fireEvent.changeText(getByPlaceholderText('What is this group for?'), 'A group for sharing repair skills and local mending sessions.');
    fireEvent.press(getByText('Create group'));

    await waitFor(() => {
      expect(mockCreateGroup).toHaveBeenCalledWith(expect.objectContaining({
        visibility: 'private',
      }));
    });
  });

  it('requires paired valid coordinates when coordinates are provided', async () => {
    const { getByPlaceholderText, getByText } = render(<NewGroupRoute />);

    fireEvent.changeText(getByPlaceholderText('Name your group'), 'Repair club');
    fireEvent.changeText(getByPlaceholderText('What is this group for?'), 'A group for sharing repair skills and local mending sessions.');
    fireEvent.changeText(getByPlaceholderText('51.5007'), '51.501');
    fireEvent.press(getByText('Create group'));

    await waitFor(() => {
      expect(Alert.alert).toHaveBeenCalledWith('Check group details', 'Enter both latitude and longitude using valid coordinate ranges.');
    });
    expect(mockCreateGroup).not.toHaveBeenCalled();
  });

  it('uploads a selected group image after creating the group', async () => {
    const { getByPlaceholderText, getByText } = render(<NewGroupRoute />);

    fireEvent.changeText(getByPlaceholderText('Name your group'), 'Repair club');
    fireEvent.changeText(getByPlaceholderText('What is this group for?'), 'A group for sharing repair skills and local mending sessions.');
    fireEvent.press(getByText('Add image'));
    await waitFor(() => expect(mockLaunchImageLibraryAsync).toHaveBeenCalled());
    fireEvent.press(getByText('Create group'));

    await waitFor(() => {
      expect(mockCreateGroup).toHaveBeenCalled();
    });
    expect(mockUploadGroupImage).toHaveBeenCalledWith(484, 'file:///tmp/group.jpg');
    expect(mockReplace).toHaveBeenCalledWith({ pathname: '/(modals)/group-detail', params: { id: '484' } });
  });

  it('loads an existing group and submits updates in edit mode', async () => {
    mockSearchParams = { id: '9' };
    mockGetGroup.mockResolvedValue({
      data: {
        id: 9,
        name: 'Garden crew',
        description: 'A group for coordinating seasonal planting and shared gardening days.',
        visibility: 'private',
        location: 'Community garden',
        latitude: 52.1,
        longitude: -6.3,
        federated_visibility: 'listed',
        image_url: '/uploads/groups/existing.jpg',
      },
    });
    mockUpdateGroup.mockResolvedValue({ data: { id: 9 } });

    const { getByDisplayValue, getByPlaceholderText, getByText } = render(<NewGroupRoute />);

    await waitFor(() => {
      expect(getByDisplayValue('Garden crew')).toBeTruthy();
    });

    expect(mockGetGroupTemplates).not.toHaveBeenCalled();
    expect(getByDisplayValue('A group for coordinating seasonal planting and shared gardening days.')).toBeTruthy();
    expect(getByDisplayValue('Community garden')).toBeTruthy();
    expect(getByDisplayValue('52.1')).toBeTruthy();
    expect(getByDisplayValue('-6.3')).toBeTruthy();

    fireEvent.changeText(getByPlaceholderText('Name your group'), 'Garden exchange');
    fireEvent.changeText(getByPlaceholderText('What is this group for?'), 'A group for coordinating tool swaps, planting help, and shared gardening days.');
    fireEvent.press(getByText('Public'));
    fireEvent.press(getByText('List in federation'));
    fireEvent.press(getByText('Update group'));

    await waitFor(() => {
      expect(mockUpdateGroup).toHaveBeenCalledWith(9, expect.objectContaining({
        name: 'Garden exchange',
        description: 'A group for coordinating tool swaps, planting help, and shared gardening days.',
        visibility: 'public',
        location: 'Community garden',
        latitude: 52.1,
        longitude: -6.3,
        federated_visibility: 'none',
      }));
    });
    expect(mockCreateGroup).not.toHaveBeenCalled();
    expect(mockReplace).toHaveBeenCalledWith({ pathname: '/(modals)/group-detail', params: { id: '9' } });
  });

  it('shows an existing group image and uploads a replacement in edit mode', async () => {
    mockSearchParams = { id: '9' };
    mockGetGroup.mockResolvedValue({
      data: {
        id: 9,
        name: 'Garden crew',
        description: 'A group for coordinating seasonal planting and shared gardening days.',
        visibility: 'private',
        location: 'Community garden',
        federated_visibility: 'listed',
        image_url: '/uploads/groups/existing.jpg',
      },
    });
    mockUpdateGroup.mockResolvedValue({ data: { id: 9 } });

    const { getByText } = render(<NewGroupRoute />);

    await waitFor(() => expect(getByText('Replace image')).toBeTruthy());
    fireEvent.press(getByText('Replace image'));
    await waitFor(() => expect(mockLaunchImageLibraryAsync).toHaveBeenCalled());
    fireEvent.press(getByText('Update group'));

    await waitFor(() => expect(mockUpdateGroup).toHaveBeenCalled());
    expect(mockUploadGroupImage).toHaveBeenCalledWith(9, 'file:///tmp/group.jpg');
  });
});
