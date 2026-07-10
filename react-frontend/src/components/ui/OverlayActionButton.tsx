// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { ButtonProps } from './Button';
import { Button } from './Button';

export const OVERLAY_ACTION_TARGET_CLASSES =
  'size-11 min-h-11 min-w-11 shrink-0 p-0';

export const OVERLAY_ACTION_REVEAL_CLASSES =
  'opacity-100 pointer-coarse:opacity-100 any-pointer-coarse:opacity-100 pointer-fine:opacity-0 pointer-fine:group-hover:opacity-100 pointer-fine:group-focus-within:opacity-100 pointer-fine:focus-visible:opacity-100 group-focus-within:opacity-100 focus-visible:opacity-100';

export interface OverlayActionButtonProps extends Omit<ButtonProps, 'isIconOnly'> {
  /**
   * Fine pointers may reveal the action on hover/focus. Coarse and hybrid
   * pointers always keep it visible so the action never depends on hover.
   */
  revealOnFinePointer?: boolean;
}

/**
 * Icon-only action for card/media overlays.
 *
 * HeroUI's built-in desktop icon sizes can be smaller than the project's
 * 44px interaction target, so this shared primitive makes that minimum an
 * explicit compatibility contract.
 */
export function OverlayActionButton({
  className,
  revealOnFinePointer = true,
  ...props
}: OverlayActionButtonProps) {
  const resolvedClassName = [
    OVERLAY_ACTION_TARGET_CLASSES,
    revealOnFinePointer ? OVERLAY_ACTION_REVEAL_CLASSES : undefined,
    className,
  ].filter(Boolean).join(' ');

  return (
    <Button
      {...props}
      isIconOnly
      className={resolvedClassName}
    />
  );
}

OverlayActionButton.displayName = 'OverlayActionButton';
