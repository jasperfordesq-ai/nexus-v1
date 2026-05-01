// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * OnboardingChoiceModal — first-visit caring-community personalization gate.
 *
 * Asks the member what brings them to the hub so we can prioritise the most
 * relevant actions. Choice is persisted to localStorage immediately and
 * fire-and-forget posted to the backend so it survives across devices.
 */

import { useCallback } from 'react';
import {
  Button,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
} from '@heroui/react';
import Compass from 'lucide-react/icons/compass';
import HandHeart from 'lucide-react/icons/hand-heart';
import Heart from 'lucide-react/icons/heart';
import type { ComponentType } from 'react';
import { useTranslation } from 'react-i18next';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

export type OnboardingChoice = 'recipient' | 'helper' | 'browse';

const STORAGE_KEY = 'caring_community_onboarding_choice';

interface OnboardingChoiceModalProps {
  isOpen: boolean;
  onChoice: (choice: OnboardingChoice) => void;
  onClose: () => void;
}

interface ChoiceCardDef {
  choice: OnboardingChoice;
  icon: ComponentType<{ className?: string }>;
  labelKey: string;
  descKey: string;
}

const CHOICES: ReadonlyArray<ChoiceCardDef> = [
  {
    choice: 'recipient',
    icon: HandHeart,
    labelKey: 'caring_community:onboarding.choice_recipient',
    descKey: 'caring_community:onboarding.choice_recipient_desc',
  },
  {
    choice: 'helper',
    icon: Heart,
    labelKey: 'caring_community:onboarding.choice_helper',
    descKey: 'caring_community:onboarding.choice_helper_desc',
  },
  {
    choice: 'browse',
    icon: Compass,
    labelKey: 'caring_community:onboarding.choice_browse',
    descKey: 'caring_community:onboarding.choice_browse_desc',
  },
];

export function persistOnboardingChoice(choice: OnboardingChoice): void {
  try {
    localStorage.setItem(STORAGE_KEY, choice);
  } catch {
    // localStorage may be unavailable (private mode); non-fatal
  }
  // Fire-and-forget — the choice is a UX hint, not load-bearing data.
  void api
    .put('/v2/caring-community/me/onboarding-choice', { choice })
    .catch((err: unknown) => {
      logError('OnboardingChoiceModal: failed to persist choice to backend', err);
    });
}

export function readStoredOnboardingChoice(): OnboardingChoice | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (raw === 'recipient' || raw === 'helper' || raw === 'browse') {
      return raw;
    }
  } catch {
    // ignore
  }
  return null;
}

export function clearStoredOnboardingChoice(): void {
  try {
    localStorage.removeItem(STORAGE_KEY);
  } catch {
    // ignore
  }
}

export function OnboardingChoiceModal({ isOpen, onChoice, onClose }: OnboardingChoiceModalProps) {
  const { t } = useTranslation('common');

  const handlePick = useCallback(
    (choice: OnboardingChoice) => {
      persistOnboardingChoice(choice);
      onChoice(choice);
    },
    [onChoice],
  );

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      size="md"
      isDismissable={false}
      isKeyboardDismissDisabled
      hideCloseButton
      placement="center"
    >
      <ModalContent>
        <ModalHeader className="flex flex-col items-start gap-1">
          <h2 className="text-xl font-semibold text-theme-primary">
            {t('caring_community:onboarding.title')}
          </h2>
          <p className="text-sm font-normal text-theme-muted">
            {t('caring_community:onboarding.intro')}
          </p>
        </ModalHeader>
        <ModalBody className="pb-6">
          <div className="flex flex-col gap-3">
            {CHOICES.map((c) => {
              const Icon = c.icon;
              return (
                <Button
                  key={c.choice}
                  type="button"
                  variant="bordered"
                  className="h-auto w-full justify-start border-theme-default bg-theme-elevated p-4 text-left"
                  onPress={() => handlePick(c.choice)}
                >
                  <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                    <Icon className="h-6 w-6" aria-hidden="true" />
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="block text-base font-semibold text-theme-primary">
                      {t(c.labelKey)}
                    </span>
                    <span className="mt-1 block text-sm leading-6 text-theme-muted">
                      {t(c.descKey)}
                    </span>
                  </span>
                </Button>
              );
            })}
          </div>
        </ModalBody>
      </ModalContent>
    </Modal>
  );
}

export default OnboardingChoiceModal;
