// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useId } from 'react';
import { useOverlayState, type UseOverlayStateProps } from '@heroui-v3/react';

export interface UseDisclosureProps {
  defaultOpen?: boolean;
  id?: string;
  isOpen?: boolean;
  onChange?: (isOpen: boolean | undefined) => void;
  onClose?: () => void;
  onOpen?: () => void;
}

export function useDisclosure({
  defaultOpen,
  id,
  isOpen,
  onChange,
  onClose,
  onOpen,
}: UseDisclosureProps = {}) {
  const generatedId = useId();
  const overlay = useOverlayState({
    defaultOpen,
    isOpen,
    onOpenChange: onChange as UseOverlayStateProps['onOpenChange'],
  });
  const disclosureId = id ?? generatedId;
  const isControlled = isOpen !== undefined;

  const handleOpen = useCallback(() => {
    overlay.open();
    onOpen?.();
  }, [onOpen, overlay]);

  const handleClose = useCallback(() => {
    overlay.close();
    onClose?.();
  }, [onClose, overlay]);

  const handleOpenChange = useCallback((nextOpen?: boolean) => {
    if (typeof nextOpen === 'boolean') {
      overlay.setOpen(nextOpen);
      if (nextOpen) {
        onOpen?.();
      } else {
        onClose?.();
      }
      return;
    }

    const willOpen = !overlay.isOpen;
    overlay.toggle();
    if (willOpen) {
      onOpen?.();
    } else {
      onClose?.();
    }
  }, [onClose, onOpen, overlay]);

  return {
    isOpen: overlay.isOpen,
    isControlled,
    onClose: handleClose,
    onOpen: handleOpen,
    onOpenChange: handleOpenChange,
    getButtonProps: (props: Record<string, unknown> = {}) => ({
      ...props,
      'aria-controls': disclosureId,
      'aria-expanded': overlay.isOpen,
      onClick: handleOpen,
    }),
    getDisclosureProps: (props: Record<string, unknown> = {}) => ({
      ...props,
      hidden: !overlay.isOpen,
      id: disclosureId,
    }),
  };
}
