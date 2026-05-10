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
          <Download className="w-5 h-5 text-indigo-500" aria-hidden="true" />
          {title}
        </ModalHeader>
        <ModalBody>
          <p className="text-sm text-theme-muted">{intro}</p>
          <ol className="space-y-3 mt-2">
            {steps.map((step, i) => (
              <li key={i} className="flex items-start gap-3">
                <span className="shrink-0 w-6 h-6 rounded-full bg-indigo-500/15 text-indigo-600 dark:text-indigo-300 text-xs font-semibold inline-flex items-center justify-center">
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
            {t('install.manual_note', "If you don't see the option, your browser may have already installed the app or your device may not support it.")}
          </p>
        </ModalBody>
        <ModalFooter>
          <Button variant="light" onPress={onClose}>
            {t('install.got_it', 'Got it')}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

function getInstructions(
  browser: BrowserKind,
  t: (key: string, fallback: string) => string,
): { title: string; intro: string; steps: Step[] } {
  const dots = <MoreVertical className="inline w-4 h-4" aria-hidden="true" />;
  const menu = <Menu className="inline w-4 h-4" aria-hidden="true" />;
  const plus = <Plus className="inline w-4 h-4" aria-hidden="true" />;

  switch (browser) {
    case 'chrome-android':
      return {
        title: t('install.android_chrome_title', 'Install on Android'),
        intro: t('install.android_chrome_intro', 'Add NEXUS to your home screen from the Chrome menu:'),
        steps: [
          { icon: dots, text: t('install.android_chrome_step_1', 'Tap the three-dot menu in the top-right of Chrome.') },
          { text: t('install.android_chrome_step_2', 'Tap "Install app" or "Add to Home screen".') },
          { text: t('install.android_chrome_step_3', 'Confirm. NEXUS will appear on your home screen as a standalone app.') },
        ],
      };
    case 'samsung':
      return {
        title: t('install.samsung_title', 'Install with Samsung Internet'),
        intro: t('install.samsung_intro', 'Add NEXUS to your home screen from the Samsung Internet menu:'),
        steps: [
          { icon: menu, text: t('install.samsung_step_1', 'Tap the menu icon at the bottom of Samsung Internet.') },
          { text: t('install.samsung_step_2', 'Tap "Add page to" → "Home screen".') },
          { text: t('install.samsung_step_3', 'Confirm. NEXUS will appear on your home screen.') },
        ],
      };
    case 'firefox-android':
      return {
        title: t('install.firefox_android_title', 'Install on Firefox for Android'),
        intro: t('install.firefox_android_intro', 'Add NEXUS to your home screen from the Firefox menu:'),
        steps: [
          { icon: dots, text: t('install.firefox_android_step_1', 'Tap the three-dot menu in Firefox.') },
          { text: t('install.firefox_android_step_2', 'Tap "Install" or "Add to Home screen".') },
          { text: t('install.firefox_android_step_3', 'Confirm to add NEXUS to your home screen.') },
        ],
      };
    case 'chrome-desktop':
      return {
        title: t('install.chrome_desktop_title', 'Install on your computer'),
        intro: t('install.chrome_desktop_intro', "Chrome may not have offered the prompt yet. You can still install NEXUS from Chrome's menu:"),
        steps: [
          { icon: dots, text: t('install.chrome_desktop_step_1', 'Click the three-dot menu in the top-right of Chrome.') },
          { text: t('install.chrome_desktop_step_2', 'Click "Cast, save, and share" → "Install page as app…", or look for an install icon in the address bar.') },
          { text: t('install.chrome_desktop_step_3', 'Click Install. NEXUS will open in its own window like a native app.') },
        ],
      };
    case 'edge-desktop':
      return {
        title: t('install.edge_desktop_title', 'Install on your computer'),
        intro: t('install.edge_desktop_intro', 'Install NEXUS as a desktop app from the Edge menu:'),
        steps: [
          { icon: dots, text: t('install.edge_desktop_step_1', 'Click the three-dot menu in the top-right of Edge.') },
          { text: t('install.edge_desktop_step_2', 'Click "Apps" → "Install this site as an app".') },
          { text: t('install.edge_desktop_step_3', 'Click Install. NEXUS will open in its own window.') },
        ],
      };
    case 'firefox-desktop':
      return {
        title: t('install.firefox_desktop_title', 'Install on your computer'),
        intro: t('install.firefox_desktop_intro', "Firefox on desktop doesn't currently support installing web apps. You can still pin NEXUS for quick access:"),
        steps: [
          { text: t('install.firefox_desktop_step_1', 'Bookmark this page (Ctrl+D) or pin the tab via right-click → "Pin Tab".') },
          { text: t('install.firefox_desktop_step_2', 'For a true installable app, try Chrome, Edge, or our mobile app.') },
        ],
      };
    case 'other':
    default:
      return {
        title: t('install.generic_title', 'Install NEXUS'),
        intro: t('install.generic_intro', "Look for an install option in your browser's menu:"),
        steps: [
          { icon: dots, text: t('install.generic_step_1', 'Open your browser menu (usually a three-dot or three-line icon).') },
          { icon: plus, text: t('install.generic_step_2', 'Look for "Install app", "Install this site", or "Add to Home screen".') },
          { text: t('install.generic_step_3', 'Confirm to add NEXUS to your device.') },
        ],
      };
  }
}

export default ManualInstallModal;
