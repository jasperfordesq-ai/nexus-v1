// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useRef, useState } from 'react';

/**
 * Long enough for HeroUI Native's bottom-sheet close animation to finish before
 * the sheet is unmounted. The library animates the sheet closed when `isOpen`
 * goes true→false (a reanimated spring, ~300ms); if we unmount synchronously
 * the moment `visible` becomes false (the previous behavior) the exit animation
 * is destroyed — the sheet just vanishes. This is the close-side counterpart to
 * the open-side deferral below.
 */
const CLOSE_ANIMATION_MS = 350;

/**
 * Drives a HeroUI Native bottom sheet from a parent `visible` boolean while
 * respecting the library's controlled lifecycle:
 *
 * - `mounted` keeps the sheet in the tree only while it is open OR animating
 *   closed, so a closed sheet does not keep a portal mounted over the screen.
 * - `open` (mapped to the sheet's `isOpen`) starts false on mount and flips
 *   true on the next tick. The library only animates the sheet IN when it
 *   observes a false→true transition while mounted — mounting already-open
 *   shows it with no animation — so this deferral is required, not a hack.
 * - On `visible` → false, `open` flips to false immediately (the library plays
 *   the close animation) and the unmount is deferred by CLOSE_ANIMATION_MS so
 *   that animation can finish. Re-opening before then cancels the unmount.
 */
export function useDeferredBottomSheetState(visible: boolean) {
  const [mounted, setMounted] = useState(false);
  const [open, setOpen] = useState(false);
  const openTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const closeTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    if (openTimerRef.current) {
      clearTimeout(openTimerRef.current);
      openTimerRef.current = null;
    }
    if (closeTimerRef.current) {
      clearTimeout(closeTimerRef.current);
      closeTimerRef.current = null;
    }

    if (!visible) {
      // Animate closed (true→false), then unmount once the animation is done.
      setOpen(false);
      closeTimerRef.current = setTimeout(() => {
        closeTimerRef.current = null;
        setMounted(false);
      }, CLOSE_ANIMATION_MS);
      return () => {
        if (closeTimerRef.current) {
          clearTimeout(closeTimerRef.current);
          closeTimerRef.current = null;
        }
      };
    }

    // Mount closed; the second effect flips `open` true so the library can
    // animate the sheet into view.
    setMounted(true);
    setOpen(false);

    return undefined;
  }, [visible]);

  useEffect(() => {
    if (!visible || !mounted) return undefined;

    openTimerRef.current = setTimeout(() => {
      openTimerRef.current = null;
      setOpen(true);
    }, 16);

    return () => {
      if (openTimerRef.current) {
        clearTimeout(openTimerRef.current);
        openTimerRef.current = null;
      }
    };
  }, [mounted, visible]);

  return { mounted, open };
}
