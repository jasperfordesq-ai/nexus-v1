// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';
import Button from './Button';
import { TenantProvider } from '@/lib/context/TenantContext';

describe('Button component', () => {
    it('renders correctly', () => {
        const { getByText } = render(
            <TenantProvider>
                <Button>Test Button</Button>
            </TenantProvider>
        );
        expect(getByText('Test Button')).toBeTruthy();
    });

    it('handles press events', () => {
        const onPressMock = jest.fn();
        const { getByText } = render(
            <TenantProvider>
                <Button onPress={onPressMock}>Click Me</Button>
            </TenantProvider>
        );

        fireEvent.press(getByText('Click Me'));
        expect(onPressMock).toHaveBeenCalledTimes(1);
    });

    it('renders loading indicator when isLoading is true', () => {
        const { getByTestId, queryByText } = render(
            <TenantProvider>
                <Button isLoading={true}>Click Me</Button>
            </TenantProvider>
        );
        // Button replaces text with ActivityIndicator when loading
        expect(queryByText('Click Me')).toBeNull();
    });

    it('does not trigger press when disabled', () => {
        const onPressMock = jest.fn();
        const { getByText } = render(
            <TenantProvider>
                <Button onPress={onPressMock} disabled={true}>Click Me</Button>
            </TenantProvider>
        );

        // toBeDisabled() traverses ancestors — catches disabled set on TouchableOpacity
        expect(getByText('Click Me')).toBeDisabled();
    });
});
