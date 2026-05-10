// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';
import { IosInstallModal } from './IosInstallModal';
import { ManualInstallModal } from './ManualInstallModal';
import type { BrowserKind } from '@/lib/installPrompt';

type Pending = { kind: 'ios' } | { kind: 'manual'; browser: BrowserKind } | null;

/**
 * Mounted once at Layout level. Listens for `nexus:install-modal` events
 * dispatched by `requestInstall()` and renders the appropriate modal.
 *
 * Hosting the modals here (instead of inside `InstallAppButton`) means they
 * survive when their trigger element unmounts — closing the mobile drawer or
 * the user-menu dropdown no longer kills the modal mid-open.
 */
export function InstallModalHost() {
  const [pending, setPending] = useState<Pending>(null);

  useEffect(() => {
    const onRequest = (e: Event) => {
      const detail = (e as CustomEvent<Pending>).detail;
      if (!detail) return;
      setPending(detail);
    };
    window.addEventListener('nexus:install-modal', onRequest);
    return () => window.removeEventListener('nexus:install-modal', onRequest);
  }, []);

  const close = () => setPending(null);

  return (
    <>
      <IosInstallModal isOpen={pending?.kind === 'ios'} onClose={close} />
      <ManualInstallModal
        isOpen={pending?.kind === 'manual'}
        onClose={close}
        browser={pending?.kind === 'manual' ? pending.browser : 'other'}
      />
    </>
  );
}

export default InstallModalHost;
