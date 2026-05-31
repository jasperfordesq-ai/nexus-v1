// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { act, fireEvent, render, waitFor } from '@testing-library/react-native';
import { Platform } from 'react-native';

import CommentSheet from './CommentSheet';

const mockGetComments = jest.fn();
const mockSubmitComment = jest.fn();
const mockShowToast = jest.fn();
const mockAwareOnBlur = jest.fn();
const mockAwareOnFocus = jest.fn();
const bottomSheetRootProps: Array<Record<string, unknown>> = [];
const bottomSheetContentProps: Array<Record<string, unknown>> = [];
const bottomSheetFlatListProps: Array<Record<string, unknown>> = [];
const bottomSheetFooterProps: Array<Record<string, unknown>> = [];

jest.mock('@gorhom/bottom-sheet', () => {
  const React = require('react');
  const { View } = require('react-native');

  return {
    BottomSheetFlatList: (props: { ListEmptyComponent?: unknown }) => {
      bottomSheetFlatListProps.push(props);
      const { ListEmptyComponent } = props;

      return (
        <View testID="comment-list">
          {typeof ListEmptyComponent === 'function'
            ? React.createElement(ListEmptyComponent as React.ComponentType)
            : (ListEmptyComponent as React.ReactElement | null)}
        </View>
      );
    },
    BottomSheetFooter: (props: { children: React.ReactNode; bottomInset?: number }) => {
      bottomSheetFooterProps.push(props);
      return <View testID="comment-footer">{props.children}</View>;
    },
  };
});

jest.mock('heroui-native', () => {
  const React = require('react');
  const { Pressable, Text, View } = require('react-native');

  const BottomSheet = (props: { children: React.ReactNode; isOpen: boolean }) => {
    bottomSheetRootProps.push(props);
    return <View testID="comment-bottom-sheet">{props.children}</View>;
  };
  BottomSheet.Portal = ({ children }: { children: React.ReactNode }) => <View>{children}</View>;
  BottomSheet.Overlay = () => <View />;
  BottomSheet.Content = (props: Record<string, unknown> & { children: React.ReactNode }) => {
    bottomSheetContentProps.push(props);
    const FooterComponent = props.footerComponent as React.ComponentType<Record<string, unknown>> | undefined;

    return (
      <View>
        {props.children}
        {FooterComponent ? <FooterComponent animatedFooterPosition={{}} /> : null}
      </View>
    );
  };
  BottomSheet.Title = ({ children }: { children: React.ReactNode }) => <Text>{children}</Text>;
  BottomSheet.Close = ({ children }: { children: React.ReactNode }) => <View>{children}</View>;

  const Button = ({
    children,
    onPress,
    accessibilityLabel,
    isDisabled,
  }: {
    children: React.ReactNode;
    onPress?: () => void;
    accessibilityLabel?: string;
    isDisabled?: boolean;
  }) => (
    <Pressable accessibilityLabel={accessibilityLabel} disabled={isDisabled} onPress={onPress}>
      {children}
    </Pressable>
  );

  return {
    BottomSheet,
    Button,
    Spinner: () => <View />,
    Surface: ({ children }: { children: React.ReactNode }) => <View>{children}</View>,
    useBottomSheetAwareHandlers: () => ({ onBlur: mockAwareOnBlur, onFocus: mockAwareOnFocus }),
  };
});

jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/ui/Input', () => {
  const React = require('react');
  const { TextInput } = require('react-native');
  return function MockInput(props: Record<string, unknown>) {
    return <TextInput testID="legacy-comment-input" {...props} />;
  };
});
jest.mock('@/components/ui/TextArea', () => {
  const React = require('react');
  const { TextInput } = require('react-native');
  return function MockTextArea(props: Record<string, unknown>) {
    return <TextInput testID="native-comment-text-area" {...props} />;
  };
});
jest.mock('@/components/ui/AppToast', () => ({
  useAppToast: () => ({ show: mockShowToast }),
}));
jest.mock('@/lib/api/comments', () => ({
  getComments: mockGetComments,
  submitComment: mockSubmitComment,
}));
jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#006FEE',
}));
jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    bg: '#0F0F0F',
    surface: '#18181B',
    text: '#F4F4F5',
    textMuted: '#A1A1AA',
    textSecondary: '#D4D4D8',
  }),
}));
jest.mock('react-native-safe-area-context', () => ({
  useSafeAreaInsets: () => ({ bottom: 24, left: 0, right: 0, top: 0 }),
}));
jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

describe('CommentSheet', () => {
  const originalPlatformOS = Platform.OS;

  beforeEach(() => {
    jest.clearAllMocks();
    mockAwareOnBlur.mockReset();
    mockAwareOnFocus.mockReset();
    bottomSheetRootProps.length = 0;
    bottomSheetContentProps.length = 0;
    bottomSheetFlatListProps.length = 0;
    bottomSheetFooterProps.length = 0;
    jest.useRealTimers();
    mockGetComments.mockResolvedValue({ data: { comments: [], count: 0 } });
    mockSubmitComment.mockResolvedValue({ data: {} });
    Object.defineProperty(Platform, 'OS', { configurable: true, get: () => originalPlatformOS });
  });

  afterEach(() => {
    Object.defineProperty(Platform, 'OS', { configurable: true, get: () => originalPlatformOS });
  });

  it('uses the shared HeroUI Native text area composer', async () => {
    const { getByTestId, queryByTestId } = render(
      <CommentSheet
        visible
        targetType="listing"
        targetId={213}
        strings={{
          actionFailedTitle: 'Comment failed',
          authorFallback: 'Member',
          empty: 'No comments yet',
          loadFailed: 'Could not load comments',
          placeholder: 'Write a comment',
          send: 'Send',
          submitFailed: 'Could not send comment',
          title: 'Comments',
        }}
        onClose={jest.fn()}
      />,
    );

    await waitFor(() => {
      expect(getByTestId('native-comment-text-area')).toBeTruthy();
      expect(queryByTestId('legacy-comment-input')).toBeNull();
    });
  });

  it('does not mount a closed sheet portal over the feed', () => {
    const { queryByTestId } = render(
      <CommentSheet
        visible={false}
        targetType="listing"
        targetId={213}
        strings={{
          actionFailedTitle: 'Comment failed',
          authorFallback: 'Member',
          empty: 'No comments yet',
          loadFailed: 'Could not load comments',
          placeholder: 'Write a comment',
          send: 'Send',
          submitFailed: 'Could not send comment',
          title: 'Comments',
        }}
        onClose={jest.fn()}
      />,
    );

    expect(queryByTestId('comment-bottom-sheet')).toBeNull();
  });

  it('shows a native toast if comments cannot load', async () => {
    mockGetComments.mockRejectedValueOnce(new Error('Network failed'));

    render(
      <CommentSheet
        visible
        targetType="listing"
        targetId={213}
        strings={{
          actionFailedTitle: 'Comment failed',
          authorFallback: 'Member',
          empty: 'No comments yet',
          loadFailed: 'Could not load comments',
          placeholder: 'Write a comment',
          send: 'Send',
          submitFailed: 'Could not send comment',
          title: 'Comments',
        }}
        onClose={jest.fn()}
      />,
    );

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(expect.objectContaining({
        description: 'Could not load comments',
        title: 'Comment failed',
      variant: 'danger',
      }));
    });
  });

  it('mounts closed before opening so HeroUI Native can snap comments into view', () => {
    jest.useFakeTimers();
    const props = {
      targetType: 'listing' as const,
      targetId: 213,
      strings: {
        actionFailedTitle: 'Comment failed',
        authorFallback: 'Member',
        empty: 'No comments yet',
        loadFailed: 'Could not load comments',
        placeholder: 'Write a comment',
        send: 'Send',
        submitFailed: 'Could not send comment',
        title: 'Comments',
      },
      onClose: jest.fn(),
    };
    const { rerender } = render(<CommentSheet {...props} visible={false} />);

    rerender(<CommentSheet {...props} visible />);

    expect(bottomSheetRootProps.at(-1)?.isOpen).toBe(false);

    act(() => {
      jest.runOnlyPendingTimers();
    });

    expect(bottomSheetRootProps.at(-1)?.isOpen).toBe(true);
  });

  it('keeps the full-screen sheet container transparent', async () => {
    render(
      <CommentSheet
        visible
        targetType="listing"
        targetId={213}
        strings={{
          actionFailedTitle: 'Comment failed',
          authorFallback: 'Member',
          empty: 'No comments yet',
          loadFailed: 'Could not load comments',
          placeholder: 'Write a comment',
          send: 'Send',
          submitFailed: 'Could not send comment',
          title: 'Comments',
        }}
        onClose={jest.fn()}
      />,
    );

    await waitFor(() => {
      expect(bottomSheetContentProps[0]?.backgroundClassName).toEqual(expect.stringContaining('bg-background'));
    });
    expect(bottomSheetContentProps[0]?.className).toBeUndefined();
    expect(bottomSheetContentProps[0]?.containerClassName).toBeUndefined();
  });

  it('pins the composer in the native bottom sheet footer above the safe area', async () => {
    const { getByTestId } = render(
      <CommentSheet
        visible
        targetType="listing"
        targetId={213}
        strings={{
          actionFailedTitle: 'Comment failed',
          authorFallback: 'Member',
          empty: 'No comments yet',
          loadFailed: 'Could not load comments',
          placeholder: 'Write a comment',
          send: 'Send',
          submitFailed: 'Could not send comment',
          title: 'Comments',
        }}
        onClose={jest.fn()}
      />,
    );

    await waitFor(() => {
      expect(getByTestId('comment-footer')).toBeTruthy();
      expect(getByTestId('native-comment-text-area')).toBeTruthy();
    });

    expect(bottomSheetContentProps[0]?.footerComponent).toEqual(expect.any(Function));
    expect(bottomSheetFooterProps[0]?.bottomInset).toBe(24);
    expect(bottomSheetFlatListProps[0]?.contentContainerStyle).toEqual(expect.objectContaining({
      paddingBottom: expect.any(Number),
    }));
    expect((bottomSheetFlatListProps[0]?.contentContainerStyle as { paddingBottom: number }).paddingBottom).toBeGreaterThanOrEqual(120);
  });

  it('does not attach the native bottom sheet blur handler on web preview', async () => {
    Object.defineProperty(Platform, 'OS', { configurable: true, get: () => 'web' });
    mockAwareOnBlur.mockImplementation(() => {
      throw new Error('currentlyFocusedInput is not a function');
    });

    const { getByTestId } = render(
      <CommentSheet
        visible
        targetType="listing"
        targetId={213}
        strings={{
          actionFailedTitle: 'Comment failed',
          authorFallback: 'Member',
          empty: 'No comments yet',
          loadFailed: 'Could not load comments',
          placeholder: 'Write a comment',
          send: 'Send',
          submitFailed: 'Could not send comment',
          title: 'Comments',
        }}
        onClose={jest.fn()}
      />,
    );

    await waitFor(() => {
      expect(getByTestId('native-comment-text-area')).toBeTruthy();
    });

    expect(() => {
      fireEvent(getByTestId('native-comment-text-area'), 'blur', { nativeEvent: { target: 1 } });
    }).not.toThrow();
    expect(mockAwareOnBlur).not.toHaveBeenCalled();
  });
});
