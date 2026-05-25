// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { View } from 'react-native';
import { BottomSheet as HeroBottomSheet } from 'heroui-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

interface BottomSheetProps {
  visible: boolean;
  onClose: () => void;
  snapPoints?: number[];
  children: React.ReactNode;
  title?: string;
}

export default function BottomSheet({
  visible,
  onClose,
  snapPoints = [300],
  children,
  title,
}: BottomSheetProps) {
  const insets = useSafeAreaInsets();

  // Convert numeric pixel snap points to percentage strings for @gorhom/bottom-sheet
  // @gorhom/bottom-sheet Content accepts snapPoints as an array of numbers or percentage strings
  const resolvedSnapPoints = snapPoints.map((h) => h + insets.bottom);

  return (
    <HeroBottomSheet
      isOpen={visible}
      onOpenChange={(open) => {
        if (!open) onClose();
      }}
    >
      <HeroBottomSheet.Portal>
        <HeroBottomSheet.Overlay isCloseOnPress />
        <HeroBottomSheet.Content snapPoints={resolvedSnapPoints as unknown as string[]}>
          {title ? (
            <View className="items-center py-2 px-4">
              <HeroBottomSheet.Title className="text-center">{title}</HeroBottomSheet.Title>
            </View>
          ) : null}
          <View className="flex-1 px-4">{children}</View>
        </HeroBottomSheet.Content>
      </HeroBottomSheet.Portal>
    </HeroBottomSheet>
  );
}
