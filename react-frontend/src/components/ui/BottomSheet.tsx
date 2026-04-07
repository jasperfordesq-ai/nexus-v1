// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BottomSheet — Reusable component that renders as a bottom sheet on mobile
 * and a centered modal on desktop.
 *
 * Uses HeroUI Modal with placement="bottom" on mobile, Framer Motion for
 * slide-up animation and drag-to-dismiss.
 */

import { useCallback } from 'react';
import { motion } from 'framer-motion';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
} from '@heroui/react';

export interface BottomSheetProps {
  isOpen: boolean;
  onClose: () => void;
  title?: string;
  children: React.ReactNode;
  snapPoints?: ('full' | 'half' | 'auto')[];
  className?: string;
}

/** Threshold in pixels — drag down past this to dismiss */
const DRAG_DISMISS_THRESHOLD = 100;

export function BottomSheet({
  isOpen,
  onClose,
  title,
  children,
  snapPoints,
  className = '',
}: BottomSheetProps) {
  // Determine max height class from first snap point
  const maxHeightClass = snapPoints?.[0] === 'full'
    ? 'max-h-[95vh]'
    : snapPoints?.[0] === 'half'
      ? 'max-h-[50vh]'
      : '';

  const handleDragEnd = useCallback(
    (_: unknown, info: { offset: { y: number }; velocity: { y: number } }) => {
      if (info.offset.y > DRAG_DISMISS_THRESHOLD || info.velocity.y > 500) {
        onClose();
      }
    },
    [onClose],
  );

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      placement="bottom"
      backdrop="blur"
      hideCloseButton
      classNames={{
        base: `bg-[var(--glass-bg)] backdrop-blur-xl border border-[var(--glass-border)] rounded-t-2xl sm:rounded-2xl ${maxHeightClass} ${className}`,
        backdrop: 'bg-black/60 backdrop-blur-sm',
        wrapper: 'sm:items-center items-end',
      }}
      motionProps={{
        variants: {
          enter: { y: 0, opacity: 1, transition: { duration: 0.3, ease: 'easeOut' } },
          exit: { y: '100%', opacity: 0, transition: { duration: 0.2, ease: 'easeIn' } },
        },
        initial: { y: '100%', opacity: 0 },
      }}
    >
      <ModalContent>
        {() => (
          <motion.div
            drag="y"
            dragConstraints={{ top: 0, bottom: 0 }}
            dragElastic={{ top: 0, bottom: 0.6 }}
            onDragEnd={handleDragEnd}
            style={{ touchAction: 'none' }}
          >
            {/* Drag handle bar (mobile only) */}
            <div className="flex justify-center pt-3 pb-1 sm:hidden cursor-grab active:cursor-grabbing">
              <div className="w-10 h-1 rounded-full bg-[var(--text-subtle)]/40" />
            </div>

            {title && (
              <ModalHeader className="text-[var(--text-primary)] text-base font-semibold px-5 pt-2 pb-3">
                {title}
              </ModalHeader>
            )}

            <ModalBody className="px-5 pb-5 pt-0">
              {children}
            </ModalBody>
          </motion.div>
        )}
      </ModalContent>
    </Modal>
  );
}

export default BottomSheet;
