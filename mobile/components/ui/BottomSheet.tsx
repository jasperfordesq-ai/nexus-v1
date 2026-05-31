// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { View } from 'react-native';
import { BottomSheet as HeroBottomSheet } from 'heroui-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTheme } from '@/lib/hooks/useTheme';
import { useDeferredBottomSheetState } from './useDeferredBottomSheetState';

interface BottomSheetProps {
  visible: boolean;
  onClose: () => void;
  snapPoints?: Array<number | string>;
  children: React.ReactNode;
  title?: string;
  childrenClassName?: string;
}

export default function BottomSheet({
  visible,
  onClose,
  snapPoints = [300],
  children,
  title,
  childrenClassName,
}: BottomSheetProps) {
  const insets = useSafeAreaInsets();
  const theme = useTheme();
  const { mounted: sheetMounted, open: sheetOpen } = useDeferredBottomSheetState(visible);

  const resolvedSnapPoints = snapPoints.map((point) => (typeof point === 'number' ? point + insets.bottom : point));
  const bottomPadding = Math.max(16, insets.bottom + 16);

  if (!sheetMounted) return null;

  return (
    <HeroBottomSheet
      isOpen={sheetOpen}
      onOpenChange={(open) => {
        if (!open && sheetOpen) onClose();
      }}
    >
      <HeroBottomSheet.Portal unstable_accessibilityContainerViewIsModal>
        <HeroBottomSheet.Overlay isCloseOnPress className="bg-black/55" />
        <HeroBottomSheet.Content
          snapPoints={resolvedSnapPoints as unknown as string[]}
          enableDynamicSizing={false}
          enableOverDrag={false}
          keyboardBehavior="extend"
          keyboardBlurBehavior="restore"
          contentContainerClassName="h-full"
          contentContainerProps={{ style: { height: '100%', backgroundColor: theme.bg } }}
          backgroundClassName="rounded-t-[30px] bg-background"
          handleClassName="rounded-t-[30px] bg-background"
          handleIndicatorClassName="bg-muted-foreground/50"
        >
          {title ? (
            <View className="items-center border-b border-border px-4 pb-3 pt-2">
              <HeroBottomSheet.Title className="text-center">{title}</HeroBottomSheet.Title>
            </View>
          ) : null}
          <View className={`flex-1 px-4 ${childrenClassName ?? ''}`} style={{ paddingBottom: bottomPadding }}>
            {children}
          </View>
        </HeroBottomSheet.Content>
      </HeroBottomSheet.Portal>
    </HeroBottomSheet>
  );
}
