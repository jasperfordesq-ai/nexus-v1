// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';
import Button from './Button';

describe('Button component', () => {
    it('renders correctly', () => {
        const { getByText } = render(<Button>Test Button</Button>);
        expect(getByText('Test Button')).toBeTruthy();
    });

    it('handles press events', () => {
        const onPressMock = jest.fn();
        const { getByText } = render(<Button onPress={onPressMock}>Click Me</Button>);

        fireEvent.press(getByText('Click Me'));
        expect(onPressMock).toHaveBeenCalledTimes(1);
    });

    it('renders loading indicator when isLoading is true', () => {
        const { queryByText } = render(<Button isLoading={true}>Click Me</Button>);
        // Button replaces text with ActivityIndicator when loading
        expect(queryByText('Click Me')).toBeNull();
    });

    it('does not trigger press when disabled', () => {
        const onPressMock = jest.fn();
        const { getByText } = render(<Button onPress={onPressMock} disabled={true}>Click Me</Button>);

        // toBeDisabled() traverses ancestors — catches disabled set on TouchableOpacity
        expect(getByText('Click Me')).toBeDisabled();
    });
});
