// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { useInstallPrompt, shouldOfferInstall } from '@/lib/installPrompt';
import { IosInstallModal } from './IosInstallModal';

interface InstallAppButtonProps {
  /** Render-prop pattern — the button chrome is supplied by the parent so we
   * can drop the install affordance into different layouts (mobile drawer
   * row, dropdown item, settings card, banner CTA) without forking the
   * component. The render fn receives an onClick handler and a label.
   *
   * If `null` is returned (no install affordance available), the parent
   * renders nothing.
   */
  children: (args: { onClick: () => void; label: string; sublabel: string }) => React.ReactNode;
}

export function InstallAppButton({ children }: InstallAppButtonProps) {
  const { t } = useTranslation('common');
  const state = useInstallPrompt();
  const [iosOpen, setIosOpen] = useState(false);

  const onClick = useCallback(() => {
    if (state.canPrompt) {
      void state.promptInstall();
      return;
    }
    if (state.isIosSafari) {
      setIosOpen(true);
    }
  }, [state]);

  if (!shouldOfferInstall(state)) return null;

  const label = t('install.cta', 'Install app');
  const sublabel = state.isIosSafari
    ? t('install.cta_ios_sub', 'Add NEXUS to your home screen')
    : t('install.cta_sub', 'Faster access, works offline');

  return (
    <>
      {children({ onClick, label, sublabel })}
      <IosInstallModal isOpen={iosOpen} onClose={() => setIosOpen(false)} />
    </>
  );
}

export default InstallAppButton;
