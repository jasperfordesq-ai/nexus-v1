// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render } from '@testing-library/react-native';

import Checkbox from './Checkbox';

jest.mock('@/lib/haptics', () => ({
  selectionAsync: jest.fn().mockResolvedValue(undefined),
}));

describe('Checkbox component', () => {
  it('forwards test and accessibility labels to the native checkbox', () => {
    const { getByLabelText, getByTestId, getByText } = render(
      <Checkbox
        checked={false}
        onPress={jest.fn()}
        label="Accept terms"
        testID="terms-checkbox"
        accessibilityLabel="Accept platform terms"
      />,
    );

    expect(getByTestId('terms-checkbox')).toBeTruthy();
    expect(getByLabelText('Accept platform terms')).toBeTruthy();
    expect(getByText('Accept terms')).toBeTruthy();
  });

  it('lets users toggle the checkbox by pressing the label', () => {
    const onPress = jest.fn();
    const { getByText } = render(
      <Checkbox checked={false} onPress={onPress} label="Accept terms" />,
    );

    fireEvent.press(getByText('Accept terms'));

    expect(onPress).toHaveBeenCalledTimes(1);
  });

  it('does not toggle from the label when disabled', () => {
    const onPress = jest.fn();
    const { getByText } = render(
      <Checkbox checked={false} onPress={onPress} label="Accept terms" disabled />,
    );

    fireEvent.press(getByText('Accept terms'));

    expect(onPress).not.toHaveBeenCalled();
  });
});
