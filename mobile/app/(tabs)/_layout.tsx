// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useRef } from 'react';
import { Animated, View } from 'react-native';
import { Tabs, usePathname } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';

import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { useRealtimeContext } from '@/lib/context/RealtimeContext';

type IoniconName = React.ComponentProps<typeof Ionicons>['name'];

/** Animated badge that scales in with a spring when it appears. */
function TabBadge({ count }: { count: number }) {
  const scale = useRef(new Animated.Value(0)).current;
  const primary = usePrimaryColor();

  useEffect(() => {
    if (count > 0) {
      scale.setValue(0);
      Animated.spring(scale, {
        toValue: 1,
        friction: 5,
        tension: 80,
        useNativeDriver: true,
      }).start();
    } else {
      scale.setValue(0);
    }
  }, [count, scale]);

  if (count <= 0) return null;

  return (
    <Animated.View
      className="absolute -top-1 -right-2.5 min-w-[18px] h-[18px] rounded-full items-center justify-center px-1"
      style={{ backgroundColor: primary, transform: [{ scale }] }}
    >
      <Animated.Text className="text-white text-[10px] font-bold text-center">
        {count > 99 ? '99+' : count}
      </Animated.Text>
    </Animated.View>
  );
}

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

  // Single source of truth from RealtimeContext — no duplicate API call
  const messagesBadgeCount = unreadMessages;

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
            tabBarIcon: ({ focused, color, size }) => (
              <View style={{ position: 'relative' }}>
                <Ionicons
                  name={focused ? iconFocused : icon}
                  size={size}
                  color={color}
                />
                {name === 'messages' && (
                  <TabBadge count={messagesBadgeCount} />
                )}
              </View>
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
