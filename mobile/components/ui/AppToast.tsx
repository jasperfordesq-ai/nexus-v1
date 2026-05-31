// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback } from 'react';
import { useToast } from 'heroui-native';

import * as Haptics from '@/lib/haptics';

type AppToastVariant = 'default' | 'accent' | 'success' | 'warning' | 'danger';
type AppToastPlacement = 'top' | 'bottom';
type ToastHideTarget = string | string[] | 'all';

interface AppToastOptions {
  title: string;
  description?: string;
  variant?: AppToastVariant;
  placement?: AppToastPlacement;
  duration?: number | 'persistent';
  actionLabel?: string;
  onActionPress?: () => void;
}

function hapticForVariant(variant: AppToastVariant) {
  if (variant === 'success') return Haptics.NotificationFeedbackType.Success;
  if (variant === 'warning') return Haptics.NotificationFeedbackType.Warning;
  if (variant === 'danger') return Haptics.NotificationFeedbackType.Error;
  return null;
}

export function useAppToast() {
  const { toast, isToastVisible } = useToast();

  const show = useCallback((options: AppToastOptions) => {
    const variant = options.variant ?? 'default';
    const feedback = hapticForVariant(variant);
    if (feedback) {
      void Haptics.notificationAsync(feedback);
    }

    return toast.show({
      actionLabel: options.actionLabel,
      description: options.description,
      duration: options.duration,
      label: options.title,
      placement: options.placement ?? 'bottom',
      variant,
      onActionPress: options.actionLabel
        ? ({ hide }) => {
            options.onActionPress?.();
            hide();
          }
        : undefined,
    });
  }, [toast]);

  const hide = useCallback((target?: ToastHideTarget) => {
    toast.hide(target);
  }, [toast]);

  return {
    hide,
    isToastVisible,
    show,
  };
}
