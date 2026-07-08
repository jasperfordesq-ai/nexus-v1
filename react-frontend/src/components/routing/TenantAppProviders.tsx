// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { ReactNode } from 'react';
import { NotificationsProvider } from '@/contexts/NotificationsContext';
import { PusherProvider } from '@/contexts/PusherContext';
import { MenuProvider } from '@/contexts/MenuContext';
import { PresenceProvider } from '@/contexts/PresenceContext';
import { PodcastPlayerProvider } from '@/contexts/PodcastPlayerContext';
import { IdleLogoutGuard } from '@/components/security/IdleLogoutGuard';

export default function TenantAppProviders({ children }: { children: ReactNode }) {
  return (
    <NotificationsProvider>
      <PusherProvider>
        <PresenceProvider>
          <MenuProvider>
            <PodcastPlayerProvider>
              {children}
            </PodcastPlayerProvider>
            <IdleLogoutGuard />
          </MenuProvider>
        </PresenceProvider>
      </PusherProvider>
    </NotificationsProvider>
  );
}
