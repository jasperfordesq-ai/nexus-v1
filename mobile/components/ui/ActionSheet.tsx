// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { View, Text } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

import BottomSheet from '@/components/ui/BottomSheet';
import Button from '@/components/ui/Button';

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
  // No manual snap-point height math — with no snapPoints the BottomSheet
  // wrapper uses dynamic sizing, so the sheet fits the action list exactly
  // (and never clips or leaves dead space).
  const handleAction = (action: Action) => {
    onClose();
    // Let the close animation start before the action fires (it may navigate
    // or open another surface).
    setTimeout(() => action.onPress(), 200);
  };

  return (
    <BottomSheet visible={visible} onClose={onClose} title={title}>
      <View className="pt-1">
        {actions.map((action, index) => (
          <Button
            key={index}
            variant="ghost"
            className={`w-full justify-start rounded-none py-4${index < actions.length - 1 ? ' border-b border-black/10' : ''}`}
            onPress={() => handleAction(action)}
            accessibilityLabel={action.label}
          >
            <View className="flex-row items-center">
              {action.icon ? (
                <Ionicons
                  name={action.icon as keyof typeof Ionicons.glyphMap}
                  size={22}
                  className={action.destructive ? 'mr-3.5 text-danger' : 'mr-3.5 text-foreground'}
                />
              ) : null}
              <Text
                className={`text-base font-medium${action.destructive ? ' text-danger' : ' text-foreground'}`}
              >
                {action.label}
              </Text>
            </View>
          </Button>
        ))}
      </View>
    </BottomSheet>
  );
}
