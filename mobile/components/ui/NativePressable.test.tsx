// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { Pressable, Text, View } from 'react-native';
import { fireEvent, render } from '@testing-library/react-native';

import NativePressable from './NativePressable';
import * as Haptics from '@/lib/haptics';

jest.mock('heroui-native', () => {
  const React = require('react');
  const { Pressable, View } = require('react-native');

  const PressableFeedback = ({
    children,
    isDisabled,
    onPress,
    accessibilityLabel,
    accessibilityRole,
  }: {
    children: React.ReactNode;
    isDisabled?: boolean;
    onPress?: () => void;
    accessibilityLabel?: string;
    accessibilityRole?: string;
  }) => (
    <Pressable
      accessibilityLabel={accessibilityLabel}
      accessibilityRole={accessibilityRole}
      disabled={isDisabled}
      onPress={isDisabled ? undefined : onPress}
      testID="native-pressable"
    >
      {children}
    </Pressable>
  );
  PressableFeedback.Scale = ({ children }: { children: React.ReactNode }) => (
    <View testID="native-pressable-scale">{children}</View>
  );
  PressableFeedback.Highlight = () => <View testID="native-pressable-highlight" />;
  PressableFeedback.Ripple = () => <View testID="native-pressable-ripple" />;

  return { PressableFeedback };
});

describe('NativePressable', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('uses HeroUI Native press feedback and app haptics for card-like taps', () => {
    const onPress = jest.fn();
    const { getByLabelText, getByTestId, getByText } = render(
      <NativePressable accessibilityLabel="Open listing" feedback="ripple" onPress={onPress}>
        <Text>Open listing</Text>
      </NativePressable>,
    );

    expect(getByText('Open listing')).toBeTruthy();
    expect(getByTestId('native-pressable-scale')).toBeTruthy();
    expect(getByTestId('native-pressable-ripple')).toBeTruthy();

    fireEvent.press(getByLabelText('Open listing'));

    expect(Haptics.impactAsync).toHaveBeenCalledWith(Haptics.ImpactFeedbackStyle.Light);
    expect(onPress).toHaveBeenCalledTimes(1);
  });

  it('does not fire haptics or press handlers while disabled', () => {
    const onPress = jest.fn();
    const { getByLabelText } = render(
      <NativePressable accessibilityLabel="Disabled item" disabled onPress={onPress}>
        <Text>Disabled item</Text>
      </NativePressable>,
    );

    fireEvent.press(getByLabelText('Disabled item'));

    expect(Haptics.impactAsync).not.toHaveBeenCalled();
    expect(onPress).not.toHaveBeenCalled();
  });
});
