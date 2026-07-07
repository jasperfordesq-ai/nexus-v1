// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useTranslation } from 'react-i18next';
import ThumbsDown from 'lucide-react/icons/thumbs-down';
import { BottomSheet } from '@/components/ui/BottomSheet';
import { Button } from '@/components/ui/Button';
import { Popover, PopoverTrigger, PopoverContent } from '@/components/ui/Popover';
import { useMediaQuery } from '@/hooks';
import type { DismissReason } from '../types';

export interface DismissReasonPopoverProps {
  onDismiss: (reason: DismissReason) => void;
  isLoading?: boolean;
  /** Controlled open state (used on the mobile BottomSheet variant). */
  isOpen: boolean;
  onOpenChange: (isOpen: boolean) => void;
}

const REASON_KEYS: DismissReason[] = ['too_far', 'not_my_skills', 'not_interested'];

function ReasonButtons({
  onDismiss,
  isLoading,
  t,
}: {
  onDismiss: (reason: DismissReason) => void;
  isLoading?: boolean;
  t: (key: string) => string;
}) {
  return (
    <div className="flex flex-col gap-1 p-2 min-w-[200px]">
      {REASON_KEYS.map((reason) => (
        <Button
          key={reason}
          variant="light"
          size="sm"
          className="justify-start text-theme-primary"
          isDisabled={isLoading}
          onPress={() => onDismiss(reason)}
        >
          {t(`dismiss_reasons.${reason}`)}
        </Button>
      ))}
    </div>
  );
}

/**
 * Trigger + reason picker for dismissing a match. Renders as a Popover on
 * >= sm viewports and a BottomSheet below sm, per the mobile UX convention
 * used elsewhere in the app.
 */
export function DismissReasonPopover({ onDismiss, isLoading, isOpen, onOpenChange }: DismissReasonPopoverProps) {
  const { t } = useTranslation('matches');
  const isDesktop = useMediaQuery('(min-width: 640px)');

  const trigger = (
    <Button
      isIconOnly
      size="sm"
      variant="light"
      aria-label={t('card.dismiss')}
      isLoading={isLoading}
      onPress={() => onOpenChange(!isOpen)}
      className="text-theme-subtle hover:text-danger"
    >
      <ThumbsDown className="w-4 h-4" aria-hidden="true" />
    </Button>
  );

  if (!isDesktop) {
    return (
      <>
        {trigger}
        <BottomSheet isOpen={isOpen} onClose={() => onOpenChange(false)} title={t('card.dismiss')}>
          <ReasonButtons onDismiss={onDismiss} isLoading={isLoading} t={t} />
        </BottomSheet>
      </>
    );
  }

  return (
    <Popover isOpen={isOpen} onOpenChange={onOpenChange} placement="top">
      <PopoverTrigger>{trigger}</PopoverTrigger>
      <PopoverContent>
        <ReasonButtons onDismiss={onDismiss} isLoading={isLoading} t={t} />
      </PopoverContent>
    </Popover>
  );
}

export default DismissReasonPopover;
