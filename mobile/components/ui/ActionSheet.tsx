// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { View, Text, Pressable, StyleSheet } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';

import BottomSheet from '@/components/ui/BottomSheet';

interface Action {
  label: string;
  icon?: string;
  onPress: () => void;
  destructive?: boolean;
}

interface ActionSheetProps {
  visible: boolean;
  onClose: () => void;
  title?: string;
  actions: Action[];
}

export default function ActionSheet({ visible, onClose, title, actions }: ActionSheetProps) {
  const snapHeight = Math.min((title ? 50 : 18) + actions.length * 56 + 24, 400);

  const handleAction = (action: Action) => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => {});
    onClose();
    setTimeout(() => action.onPress(), 200);
  };

  return (
    <BottomSheet visible={visible} onClose={onClose} snapPoints={[snapHeight]} title={title}>
      <View className="pt-1">
        {actions.map((action, index) => (
          <Pressable
            key={index}
            style={[
              styles.actionRow,
              index < actions.length - 1 ? styles.actionBorder : undefined,
            ]}
            onPress={() => handleAction(action)}
            accessibilityLabel={action.label}
            accessibilityRole="button"
          >
            {action.icon ? (
              <Ionicons
                name={action.icon as keyof typeof Ionicons.glyphMap}
                size={22}
                className={action.destructive ? 'text-danger mr-3.5' : 'text-foreground mr-3.5'}
              />
            ) : null}
            <Text
              className={`text-base font-medium${action.destructive ? ' text-danger' : ' text-foreground'}`}
            >
              {action.label}
            </Text>
          </Pressable>
        ))}
      </View>
    </BottomSheet>
  );
}

const styles = StyleSheet.create({
  actionRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 16,
  },
  actionBorder: {
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: 'rgba(0,0,0,0.1)',
  },
});
