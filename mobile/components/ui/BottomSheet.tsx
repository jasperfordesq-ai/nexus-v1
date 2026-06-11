// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { View } from 'react-native';
import { BottomSheet as HeroBottomSheet } from 'heroui-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { getRootBottomInset } from '@/lib/ui/rootInsets';
import { useDeferredBottomSheetState } from './useDeferredBottomSheetState';

interface BottomSheetProps {
  visible: boolean;
  onClose: () => void;
  /**
   * Explicit snap points. Numbers are pixel heights (the bottom safe-area inset
   * is added so content isn't clipped by the home indicator); strings are
   * percentages (e.g. '90%'). Omit entirely to let the library size the sheet
   * to its content (dynamic sizing) — no manual height math required.
   */
  snapPoints?: (number | string)[];
  children: React.ReactNode;
  title?: string;
  childrenClassName?: string;
}

export default function BottomSheet({
  visible,
  onClose,
  snapPoints,
  children,
  title,
  childrenClassName,
}: BottomSheetProps) {
  const insets = useSafeAreaInsets();
  const { mounted: sheetMounted, open: sheetOpen } = useDeferredBottomSheetState(visible);

  // Inside Android `presentation: 'modal'` screens useSafeAreaInsets()
  // reports bottom: 0, which put sheet footers underneath the system nav
  // bar. Fall back to the inset recorded at the app root.
  const bottomInset = Math.max(insets.bottom, getRootBottomInset());

  // With explicit snap points, honour them (numbers get the bottom inset added
  // so content isn't clipped). With none, let the library size the sheet to its
  // content — no magic height math, no clipping, no dead space.
  const hasSnapPoints = Array.isArray(snapPoints) && snapPoints.length > 0;
  const resolvedSnapPoints = hasSnapPoints
    ? snapPoints!.map((point) => (typeof point === 'number' ? point + bottomInset : point))
    : undefined;
  const bottomPadding = Math.max(16, bottomInset + 16);

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
          snapPoints={resolvedSnapPoints}
          enableDynamicSizing={!hasSnapPoints}
          enableOverDrag={false}
          keyboardBehavior="extend"
          keyboardBlurBehavior="restore"
          contentContainerClassName={hasSnapPoints ? 'h-full bg-background' : 'bg-background'}
          backgroundClassName="rounded-t-[30px] bg-background"
          handleClassName="rounded-t-[30px] bg-background"
          handleIndicatorClassName="bg-muted-foreground/50"
        >
          {title ? (
            <View className="items-center border-b border-border px-4 pb-3 pt-2">
              <HeroBottomSheet.Title className="text-center">{title}</HeroBottomSheet.Title>
            </View>
          ) : null}
          <View
            className={`px-4 ${hasSnapPoints ? 'flex-1 ' : ''}${childrenClassName ?? ''}`}
            style={{ paddingBottom: bottomPadding }}
          >
            {children}
          </View>
        </HeroBottomSheet.Content>
      </HeroBottomSheet.Portal>
    </HeroBottomSheet>
  );
}
