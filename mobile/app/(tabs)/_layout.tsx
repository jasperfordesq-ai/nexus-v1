// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect } from 'react';
import { Tabs, usePathname } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';

import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { useRealtimeContext } from '@/lib/context/RealtimeContext';

type IoniconName = React.ComponentProps<typeof Ionicons>['name'];

interface TabConfig {
  name: string;
  i18nKey: string;
  icon: IoniconName;
  iconFocused: IoniconName;
}

const TABS_CONFIG: TabConfig[] = [
  { name: 'home',      i18nKey: 'common:tabs.home',      icon: 'home-outline',    iconFocused: 'home' },
  { name: 'exchanges', i18nKey: 'common:tabs.listings',   icon: 'storefront-outline', iconFocused: 'storefront' },
  { name: 'events',    i18nKey: 'common:tabs.events',     icon: 'calendar-outline', iconFocused: 'calendar' },
  { name: 'messages',  i18nKey: 'common:tabs.messages',   icon: 'chatbubble-outline', iconFocused: 'chatbubble' },
  { name: 'profile',   i18nKey: 'common:tabs.profile',    icon: 'person-outline',  iconFocused: 'person' },
];

export default function TabsLayout() {
  const { t } = useTranslation();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const { unreadMessages, resetUnread } = useRealtimeContext();
  const pathname = usePathname();
  const insets = useSafeAreaInsets();

  // Clear the badge whenever the user navigates to the Messages tab
  useEffect(() => {
    if (pathname === '/messages') {
      resetUnread();
    }
  }, [pathname, resetUnread]);

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: primary,
        tabBarInactiveTintColor: theme.textMuted,
        tabBarStyle: {
          backgroundColor: theme.surface,
          borderTopColor: theme.border,
          borderTopWidth: 1,
          paddingBottom: (insets.bottom || 0) + 4,
          paddingTop: 4,
          height: 60 + (insets.bottom || 0),
        },
        tabBarLabelStyle: {
          fontSize: 11,
          fontWeight: '500',
        },
      }}
    >
      {TABS_CONFIG.map(({ name, i18nKey, icon, iconFocused }) => (
        <Tabs.Screen
          key={name}
          name={name}
          options={{
            title: t(i18nKey),
            tabBarBadge: name === 'messages' && unreadMessages > 0 ? unreadMessages : undefined,
            tabBarBadgeStyle: name === 'messages' && unreadMessages > 0 ? { backgroundColor: primary } : undefined,
            tabBarIcon: ({ focused, color, size }) => (
              <Ionicons
                name={focused ? iconFocused : icon}
                size={size}
                color={color}
              />
            ),
          }}
        />
      ))}
      {/* Hide auxiliary tabs from the tab bar — navigated to programmatically */}
      <Tabs.Screen name="members" options={{ href: null }} />
      <Tabs.Screen name="groups" options={{ href: null }} />
      <Tabs.Screen name="search" options={{ href: null }} />
    </Tabs>
  );
}
