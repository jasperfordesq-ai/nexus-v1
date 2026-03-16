// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useRef } from 'react';
import { Stack, router } from 'expo-router';
import * as Notifications from 'expo-notifications';
import { StatusBar } from 'expo-status-bar';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import '@/lib/i18n'; // initialise i18next before any screen renders
import { AuthProvider, useAuthContext } from '@/lib/context/AuthContext';
import { TenantProvider } from '@/lib/context/TenantContext';
import { RealtimeProvider } from '@/lib/context/RealtimeContext';
import ErrorBoundary from '@/components/ErrorBoundary';
import { navigateToLink } from '@/lib/utils/navigateToLink';
import * as Sentry from '@sentry/react-native';

Sentry.init({
  dsn: process.env.EXPO_PUBLIC_SENTRY_DSN ?? '',
  // Sample 10% of traces in production to reduce Sentry quota usage
  tracesSampleRate: 0.1,
  enabled: !!process.env.EXPO_PUBLIC_SENTRY_DSN,
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

  // Queue deep link from push notification taps — only navigate once auth resolves.
  const pendingDeepLinkRef = useRef<string | null>(null);

  useEffect(() => {
    const subscription = Notifications.addNotificationResponseReceivedListener((response) => {
      const data = response.notification.request.content.data as { link?: string } | undefined;
      if (data?.link) {
        pendingDeepLinkRef.current = data.link;
      }
    });
    return () => subscription.remove();
  }, []);

  // Process queued deep link only after auth state is resolved and user is authenticated
  useEffect(() => {
    if (isLoading || !isAuthenticated) return;
    const link = pendingDeepLinkRef.current;
    if (link) {
      pendingDeepLinkRef.current = null;
      navigateToLink(link);
    }
  }, [isLoading, isAuthenticated]);

  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Screen name="index" />
      <Stack.Screen name="(auth)" />
      <Stack.Screen name="(tabs)" />
      <Stack.Screen
        name="(modals)/new-exchange"
        options={{ presentation: 'modal', headerShown: true, title: '' }}
      />
      <Stack.Screen
        name="(modals)/exchange-detail"
        options={{ presentation: 'modal', headerShown: true, title: '' }}
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
        options={{ presentation: 'modal', headerShown: true, title: '' }}
      />
      <Stack.Screen
        name="(modals)/wallet"
        options={{ presentation: 'modal', headerShown: true, title: '' }}
      />
      <Stack.Screen
        name="(modals)/settings"
        options={{ presentation: 'modal', headerShown: true, title: '' }}
      />
      <Stack.Screen
        name="(modals)/edit-profile"
        options={{ presentation: 'modal', headerShown: true, title: '' }}
      />
      <Stack.Screen
        name="(modals)/event-detail"
        options={{ presentation: 'modal', headerShown: true, title: '' }}
      />
      <Stack.Screen
        name="(modals)/members"
        options={{ presentation: 'modal', headerShown: true, title: '' }}
      />
      <Stack.Screen
        name="(modals)/change-password"
        options={{ presentation: 'modal', headerShown: true, title: '' }}
      />
    </Stack>
  );
}
