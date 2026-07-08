// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { lazy, Suspense, useCallback, useEffect, useState, type ReactNode } from 'react';
import { MenuContext, emptyMenuContext, type MenuContextValue } from '@/contexts/MenuContextCore';

const PublicMenuHydrator = lazy(() =>
  import('@/contexts/MenuContext').then((module) => ({
    default: module.MenuHydrator,
  })),
);

type IdleWindow = Window & {
  requestIdleCallback?: (
    callback: IdleRequestCallback,
    options?: IdleRequestOptions,
  ) => number;
};

export default function TenantPublicProviders({ children }: { children: ReactNode }) {
  const [canLoadMenus, setCanLoadMenus] = useState(false);
  const [menuValue, setMenuValue] = useState<MenuContextValue>(emptyMenuContext);
  const handleMenuChange = useCallback((value: MenuContextValue) => {
    setMenuValue(value);
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      const idleWindow = window as IdleWindow;
      if (typeof idleWindow.requestIdleCallback === 'function') {
        idleWindow.requestIdleCallback(() => setCanLoadMenus(true), { timeout: 4000 });
        return;
      }

      setCanLoadMenus(true);
    }, 2500);

    return () => window.clearTimeout(timer);
  }, []);

  return (
    <MenuContext.Provider value={menuValue}>
      {children}
      {canLoadMenus && (
        <Suspense fallback={null}>
          <PublicMenuHydrator onChange={handleMenuChange} />
        </Suspense>
      )}
    </MenuContext.Provider>
  );
}
