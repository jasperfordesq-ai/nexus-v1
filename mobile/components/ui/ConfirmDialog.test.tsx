// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { Pressable, Text, View } from 'react-native';
import { fireEvent, render } from '@testing-library/react-native';

import ConfirmDialog from './ConfirmDialog';

jest.mock('heroui-native', () => {
  const React = require('react');
  const { Pressable, Text, View } = require('react-native');

  const Dialog = ({ children, isOpen }: { children: React.ReactNode; isOpen: boolean }) =>
    isOpen ? <View testID="dialog-root">{children}</View> : null;
  Dialog.Portal = ({ children }: { children: React.ReactNode }) => <View testID="dialog-portal">{children}</View>;
  Dialog.Overlay = (props: Record<string, unknown>) => <View testID="dialog-overlay" {...props} />;
  Dialog.Content = ({ children }: { children: React.ReactNode }) => <View testID="dialog-content">{children}</View>;
  Dialog.Title = ({ children }: { children: React.ReactNode }) => <Text>{children}</Text>;
  Dialog.Description = ({ children }: { children: React.ReactNode }) => <Text>{children}</Text>;

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
    <Pressable
      accessibilityLabel={accessibilityLabel}
      accessibilityRole="button"
      disabled={isDisabled}
      onPress={onPress}
    >
      {children}
    </Pressable>
  );
  Button.Label = ({ children }: { children: React.ReactNode }) => <Text>{children}</Text>;

  return {
    Button,
    Dialog,
    Spinner: () => <View testID="confirm-spinner" />,
  };
});

describe('ConfirmDialog', () => {
  it('renders a native dialog with caller-provided copy and actions', () => {
    const onClose = jest.fn();
    const onConfirm = jest.fn();

    const { getByText, getByLabelText } = render(
      <ConfirmDialog
        visible
        title="Delete listing?"
        message="This cannot be undone."
        cancelLabel="Keep it"
        confirmLabel="Delete"
        onClose={onClose}
        onConfirm={onConfirm}
      />,
    );

    expect(getByText('Delete listing?')).toBeTruthy();
    expect(getByText('This cannot be undone.')).toBeTruthy();

    fireEvent.press(getByLabelText('Delete'));
    expect(onConfirm).toHaveBeenCalledTimes(1);

    fireEvent.press(getByLabelText('Keep it'));
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('does not render while closed', () => {
    const { queryByText } = render(
      <ConfirmDialog
        visible={false}
        title="Hidden dialog"
        cancelLabel="Cancel"
        confirmLabel="Confirm"
        onClose={jest.fn()}
        onConfirm={jest.fn()}
      />,
    );

    expect(queryByText('Hidden dialog')).toBeNull();
  });
});
