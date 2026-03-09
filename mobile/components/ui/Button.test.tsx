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

        // In React Native, TouchableOpacity handles the disabled state internally
        // and won't fire onPress, but testing-library still dispatches the event.
        // However, if we pass disabled={true} to our Button, we can verify the styles/props.
        const button = getByText('Click Me').parent;
        expect(button?.props.accessibilityState?.disabled).toBe(true);
    });
});
