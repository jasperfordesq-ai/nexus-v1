// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect } from 'react';
import { Stack, router } from 'expo-router';
import * as Notifications from 'expo-notifications';
import { StatusBar } from 'expo-status-bar';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { AuthProvider, useAuthContext } from '@/lib/context/AuthContext';
import { TenantProvider } from '@/lib/context/TenantContext';
import { RealtimeProvider } from '@/lib/context/RealtimeContext';
import ErrorBoundary from '@/components/ErrorBoundary';
import * as Sentry from '@sentry/react-native';

Sentry.init({
  dsn: 'https://placeholder@sentry.io/1234567',
  tracesSampleRate: 1.0,
});

/**
 * Root layout — wraps the entire app in providers and handles the
 * initial auth redirect (auth check → home or login).
 */
function RootLayout() {
  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <SafeAreaProvider>
        <ErrorBoundary>
          <TenantProvider>
            <AuthProvider>
              <RealtimeProvider>
                <StatusBar style="auto" />
                <RootNavigator />
              </RealtimeProvider>
            </AuthProvider>
          </TenantProvider>
        </ErrorBoundary>
      </SafeAreaProvider>
    </GestureHandlerRootView>
  );
}

export default Sentry.wrap(RootLayout);

/**
 * Maps a web-format deep-link (e.g. /messages/123) to the appropriate
 * mobile screen. Shared by foreground notification taps and background taps.
 */
function navigateToLink(link: string): void {
  const match = link.match(/^\/([^/]+)(?:\/(\d+))?/);
  if (!match) return;
  const [, section, id] = match;
  switch (section) {
    case 'exchanges':
      if (id) router.push({ pathname: '/(modals)/exchange-detail', params: { id } });
      break;
    case 'events':
      if (id) router.push({ pathname: '/(modals)/event-detail', params: { id } });
      break;
    case 'members':
      if (id) router.push({ pathname: '/(modals)/member-profile', params: { id } });
      break;
    case 'messages':
      if (id) router.push({ pathname: '/(modals)/thread', params: { id } });
      else router.push('/(tabs)/messages');
      break;
    default:
      break;
  }
}

/**
 * Handles the redirect logic after auth state resolves.
 * Separated from RootLayout so it can consume AuthContext.
 */
function RootNavigator() {
  const { isLoading, isAuthenticated } = useAuthContext();

  useEffect(() => {
    if (isLoading) return;
    if (isAuthenticated) {
      router.replace('/(tabs)/home');
    } else {
      router.replace('/(auth)/login');
    }
  }, [isLoading, isAuthenticated]);

  // Handle taps on push notifications received while app was backgrounded/killed
  useEffect(() => {
    const subscription = Notifications.addNotificationResponseReceivedListener((response) => {
      const data = response.notification.request.content.data as { link?: string } | undefined;
      if (data?.link) navigateToLink(data.link);
    });
    return () => subscription.remove();
  }, []);

  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Screen name="index" />
      <Stack.Screen name="(auth)" />
      <Stack.Screen name="(tabs)" />
      <Stack.Screen
        name="(modals)/new-exchange"
        options={{ presentation: 'modal', headerShown: true, title: 'New Exchange' }}
      />
      <Stack.Screen
        name="(modals)/exchange-detail"
        options={{ presentation: 'modal', headerShown: true, title: 'Exchange' }}
      />
      <Stack.Screen
        name="(modals)/thread"
        options={{ presentation: 'modal', headerShown: true, title: '' }}
      />
      <Stack.Screen
        name="(modals)/member-profile"
        options={{ presentation: 'modal', headerShown: true, title: '' }}
      />
      <Stack.Screen
        name="(modals)/notifications"
        options={{ presentation: 'modal', headerShown: true, title: 'Notifications' }}
      />
      <Stack.Screen
        name="(modals)/wallet"
        options={{ presentation: 'modal', headerShown: true, title: 'Wallet' }}
      />
      <Stack.Screen
        name="(modals)/settings"
        options={{ presentation: 'modal', headerShown: true, title: 'Settings' }}
      />
      <Stack.Screen
        name="(modals)/edit-profile"
        options={{ presentation: 'modal', headerShown: true, title: 'Edit Profile' }}
      />
      <Stack.Screen
        name="(modals)/event-detail"
        options={{ presentation: 'modal', headerShown: true, title: 'Event' }}
      />
      <Stack.Screen
        name="(modals)/members"
        options={{ presentation: 'modal', headerShown: true, title: 'Members' }}
      />
      <Stack.Screen
        name="(modals)/change-password"
        options={{ presentation: 'modal', headerShown: true, title: 'Change Password' }}
      />
    </Stack>
  );
}
