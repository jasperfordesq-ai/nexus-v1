// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useRef } from 'react';
import { LogBox } from 'react-native';
import { Stack, router } from 'expo-router';
import * as Notifications from 'expo-notifications';
import { StatusBar } from 'expo-status-bar';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { useTranslation } from 'react-i18next';
import '@/lib/i18n'; // initialise i18next before any screen renders
import { validateEnv } from '@/lib/env';
import { AuthProvider, useAuthContext } from '@/lib/context/AuthContext';
import { TenantProvider } from '@/lib/context/TenantContext';
import { RealtimeProvider } from '@/lib/context/RealtimeContext';
import ErrorBoundary from '@/components/ErrorBoundary';
import { navigateToLink } from '@/lib/utils/navigateToLink';
import * as Sentry from '@sentry/react-native';

// Validate environment variables at startup — logs warnings for missing config
validateEnv();

// Suppress known non-fatal dev-mode warnings that block the UI in Expo Go
LogBox.ignoreLogs([
  'expo-notifications',
  'expo-av',
  'VirtualizedLists should never be nested',
  'Each child in a list should have a unique',
  'Encountered two children with the same key',
  'Non-serializable values were found in the navigation state',
]);

// Patch console.error to prevent React dev-mode warnings from triggering
// the full-screen error overlay in Expo Go. These are non-fatal warnings
// (duplicate keys, deprecation notices) that block the entire UI.
if (__DEV__) {
  const originalConsoleError = console.error;
  console.error = (...args: unknown[]) => {
    const msg = typeof args[0] === 'string' ? args[0] : '';
    if (
      msg.includes('Encountered two children with the same key') ||
      msg.includes('Each child in a list should have a unique') ||
      msg.includes('expo-notifications') ||
      msg.includes('expo-av')
    ) {
      // Downgrade to warning so the error overlay doesn't appear
      console.warn('[suppressed]', ...args);
      return;
    }
    originalConsoleError(...args);
  };
}

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
  const { t } = useTranslation();
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

  // Shared options for regular modal screens: slide up from bottom, swipe-to-dismiss
  const modalOptions = {
    presentation: 'modal' as const,
    headerShown: true,
    animation: 'slide_from_bottom' as const,
    gestureEnabled: true,
    gestureDirection: 'vertical' as const,
    contentStyle: { backgroundColor: 'transparent' },
  };

  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Screen name="index" />
      <Stack.Screen name="(auth)" />
      <Stack.Screen name="(tabs)" />
      <Stack.Screen
        name="(modals)/new-exchange"
        options={{ ...modalOptions, title: t('exchanges:newTitle') }}
      />
      <Stack.Screen
        name="(modals)/exchange-detail"
        options={{ ...modalOptions, title: t('exchanges:detailTitle') }}
      />
      <Stack.Screen
        name="(modals)/thread"
        options={{ ...modalOptions, title: t('messages:threadTitle') }}
      />
      <Stack.Screen
        name="(modals)/member-profile"
        options={{ ...modalOptions, title: t('members:profileTitle') }}
      />
      <Stack.Screen
        name="(modals)/notifications"
        options={{ ...modalOptions, title: t('notifications:title') }}
      />
      <Stack.Screen
        name="(modals)/wallet"
        options={{ ...modalOptions, title: t('wallet:title') }}
      />
      <Stack.Screen
        name="(modals)/settings"
        options={{ ...modalOptions, title: t('settings:title') }}
      />
      <Stack.Screen
        name="(modals)/edit-profile"
        options={{ ...modalOptions, title: t('profile:editTitle') }}
      />
      <Stack.Screen
        name="(modals)/event-detail"
        options={{ ...modalOptions, title: t('events:detailTitle') }}
      />
      <Stack.Screen
        name="(modals)/members"
        options={{ ...modalOptions, title: t('members:title') }}
      />
      <Stack.Screen
        name="(modals)/change-password"
        options={{ ...modalOptions, title: t('settings:changePasswordTitle') }}
      />
      <Stack.Screen
        name="(modals)/group-detail"
        options={{ ...modalOptions, title: t('groups:detailTitle') }}
      />
      <Stack.Screen
        name="(modals)/blog"
        options={{ ...modalOptions, title: t('blog:title') }}
      />
      <Stack.Screen
        name="(modals)/blog-post"
        options={{ ...modalOptions, title: t('blog:postTitle') }}
      />
      <Stack.Screen
        name="(modals)/gamification"
        options={{ ...modalOptions, title: t('gamification:title') }}
      />
      <Stack.Screen
        name="(modals)/goals"
        options={{ ...modalOptions, title: t('goals:title') }}
      />
      <Stack.Screen
        name="(modals)/chat"
        options={{ ...modalOptions, title: t('chat:title') }}
      />
      <Stack.Screen
        name="(modals)/volunteering"
        options={{ ...modalOptions, title: t('volunteering:title') }}
      />
      <Stack.Screen
        name="(modals)/volunteering-detail"
        options={{ ...modalOptions, title: t('volunteering:detailTitle') }}
      />
      <Stack.Screen
        name="(modals)/jobs"
        options={{ ...modalOptions, title: t('jobs:title') }}
      />
      <Stack.Screen
        name="(modals)/job-detail"
        options={{ ...modalOptions, title: t('jobs:detailTitle') }}
      />
      <Stack.Screen
        name="(modals)/organisations"
        options={{ ...modalOptions, title: t('organisations:title') }}
      />
      <Stack.Screen
        name="(modals)/organisation-detail"
        options={{ ...modalOptions, title: t('organisations:detailTitle') }}
      />
      <Stack.Screen
        name="(modals)/endorsements"
        options={{ ...modalOptions, title: t('endorsements:title') }}
      />
      <Stack.Screen
        name="(modals)/federation"
        options={{ ...modalOptions, title: t('federation:title') }}
      />
      <Stack.Screen
        name="(modals)/federation-partner"
        options={{ ...modalOptions, title: t('federation:partnerTitle') }}
      />
      <Stack.Screen
        name="(modals)/groups"
        options={{ ...modalOptions, title: t('groups:title') }}
      />
      <Stack.Screen
        name="(modals)/search"
        options={{ ...modalOptions, title: t('search:title') }}
      />
      <Stack.Screen
        name="(modals)/image-viewer"
        options={{
          presentation: 'fullScreenModal',
          headerShown: false,
          gestureEnabled: true,
          gestureDirection: 'vertical',
          contentStyle: { backgroundColor: 'transparent' },
        }}
      />
    </Stack>
  );
}
