// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React, { useMemo } from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';

import { useTheme, type Theme } from '@/lib/hooks/useTheme';
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

export default function ActionSheet({
  visible,
  onClose,
  title,
  actions,
}: ActionSheetProps) {
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

  // Calculate snap height: header (~50) + actions (56 each) + padding
  const snapHeight = Math.min(
    (title ? 50 : 18) + actions.length * 56 + 24,
    400,
  );

  const handleAction = (action: Action) => {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    onClose();
    // Small delay so the sheet closes before the action fires
    setTimeout(() => action.onPress(), 200);
  };

  return (
    <BottomSheet
      visible={visible}
      onClose={onClose}
      snapPoints={[snapHeight]}
      title={title}
    >
      <View style={styles.list}>
        {actions.map((action, index) => (
          <TouchableOpacity
            key={index}
            style={[
              styles.actionRow,
              index < actions.length - 1 && styles.actionBorder,
            ]}
            activeOpacity={0.6}
            onPress={() => handleAction(action)}
            accessibilityLabel={action.label}
            accessibilityRole="button"
          >
            {action.icon && (
              <Ionicons
                name={action.icon as keyof typeof Ionicons.glyphMap}
                size={22}
                color={action.destructive ? theme.error : theme.text}
                style={styles.actionIcon}
              />
            )}
            <Text
              style={[
                styles.actionLabel,
                action.destructive && { color: theme.error },
              ]}
            >
              {action.label}
            </Text>
          </TouchableOpacity>
        ))}
      </View>
    </BottomSheet>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    list: {
      paddingTop: 4,
    },
    actionRow: {
      flexDirection: 'row',
      alignItems: 'center',
      paddingVertical: 16,
    },
    actionBorder: {
      borderBottomWidth: StyleSheet.hairlineWidth,
      borderBottomColor: theme.borderSubtle,
    },
    actionIcon: {
      marginRight: 14,
    },
    actionLabel: {
      fontSize: 16,
      color: theme.text,
      fontWeight: '500',
    },
  });
}
