// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Modal, ModalContent, ModalHeader, ModalBody, ModalFooter, Button } from '@heroui/react';
import { useTranslation } from 'react-i18next';
import MoreVertical from 'lucide-react/icons/more-vertical';
import Menu from 'lucide-react/icons/menu';
import Download from 'lucide-react/icons/download';
import Plus from 'lucide-react/icons/plus';
import type { BrowserKind } from '@/lib/installPrompt';

interface ManualInstallModalProps {
  isOpen: boolean;
  onClose: () => void;
  browser: BrowserKind;
}

interface Step {
  text: string;
  icon?: React.ReactNode;
}

export function ManualInstallModal({ isOpen, onClose, browser }: ManualInstallModalProps) {
  const { t } = useTranslation('common');

  const { title, intro, steps } = getInstructions(browser, t);

  return (
    <Modal isOpen={isOpen} onClose={onClose} placement="center" size="md" backdrop="blur">
      <ModalContent>
        <ModalHeader className="flex items-center gap-2">
          <Download className="w-5 h-5 text-primary" aria-hidden="true" />
          {title}
        </ModalHeader>
        <ModalBody>
          <p className="text-sm text-theme-muted">{intro}</p>
          <ol className="space-y-3 mt-2">
            {steps.map((step, i) => (
              <li key={i} className="flex items-start gap-3">
                <span className="shrink-0 w-6 h-6 rounded-full bg-primary/15 text-primary text-xs font-semibold inline-flex items-center justify-center">
                  {i + 1}
                </span>
                <span className="text-sm flex flex-wrap items-center gap-1">
                  {step.icon}
                  {step.text}
                </span>
              </li>
            ))}
          </ol>
          <p className="text-xs text-theme-muted mt-3">
            {t('install.manual_note')}
          </p>
        </ModalBody>
        <ModalFooter>
          <Button variant="light" onPress={onClose}>
            {t('install.got_it')}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

function getInstructions(
  browser: BrowserKind,
  t: (key: string) => string,
): { title: string; intro: string; steps: Step[] } {
  const dots = <MoreVertical className="inline w-4 h-4" aria-hidden="true" />;
  const menu = <Menu className="inline w-4 h-4" aria-hidden="true" />;
  const plus = <Plus className="inline w-4 h-4" aria-hidden="true" />;

  switch (browser) {
    case 'chrome-android':
      return {
        title: t('install.android_chrome_title'),
        intro: t('install.android_chrome_intro'),
        steps: [
          { icon: dots, text: t('install.android_chrome_step_1') },
          { text: t('install.android_chrome_step_2') },
          { text: t('install.android_chrome_step_3') },
        ],
      };
    case 'samsung':
      return {
        title: t('install.samsung_title'),
        intro: t('install.samsung_intro'),
        steps: [
          { icon: menu, text: t('install.samsung_step_1') },
          { text: t('install.samsung_step_2') },
          { text: t('install.samsung_step_3') },
        ],
      };
    case 'firefox-android':
      return {
        title: t('install.firefox_android_title'),
        intro: t('install.firefox_android_intro'),
        steps: [
          { icon: dots, text: t('install.firefox_android_step_1') },
          { text: t('install.firefox_android_step_2') },
          { text: t('install.firefox_android_step_3') },
        ],
      };
    case 'chrome-desktop':
      return {
        title: t('install.chrome_desktop_title'),
        intro: t('install.chrome_desktop_intro'),
        steps: [
          { icon: dots, text: t('install.chrome_desktop_step_1') },
          { text: t('install.chrome_desktop_step_2') },
          { text: t('install.chrome_desktop_step_3') },
        ],
      };
    case 'edge-desktop':
      return {
        title: t('install.edge_desktop_title'),
        intro: t('install.edge_desktop_intro'),
        steps: [
          { icon: dots, text: t('install.edge_desktop_step_1') },
          { text: t('install.edge_desktop_step_2') },
          { text: t('install.edge_desktop_step_3') },
        ],
      };
    case 'firefox-desktop':
      return {
        title: t('install.firefox_desktop_title'),
        intro: t('install.firefox_desktop_intro'),
        steps: [
          { text: t('install.firefox_desktop_step_1') },
          { text: t('install.firefox_desktop_step_2') },
        ],
      };
    case 'other':
    default:
      return {
        title: t('install.generic_title'),
        intro: t('install.generic_intro'),
        steps: [
          { icon: dots, text: t('install.generic_step_1') },
          { icon: plus, text: t('install.generic_step_2') },
          { text: t('install.generic_step_3') },
        ],
      };
  }
}

export default ManualInstallModal;
