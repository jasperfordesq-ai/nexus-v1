// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useRef } from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { CaringPanelSidebar } from './components/CaringPanelSidebar';
import { CaringPanelHeader } from './components/CaringPanelHeader';
import { CaringPanelBreadcrumbs } from './components/CaringPanelBreadcrumbs';

export function CaringLayout() {
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const [mobileDrawerOpen, setMobileDrawerOpen] = useState(false);
  const drawerRef = useRef<HTMLDivElement | null>(null);
  const returnFocusRef = useRef<HTMLElement | null>(null);
  const location = useLocation();
  const { t } = useTranslation('caring_community');

  const closeMobileDrawer = () => {
    setMobileDrawerOpen(false);
  };

  const toggleMobileDrawer = () => {
    setMobileDrawerOpen((prev) => {
      if (!prev) {
        returnFocusRef.current = document.activeElement instanceof HTMLElement
          ? document.activeElement
          : null;
      }
      return !prev;
    });
  };

  useEffect(() => {
    setMobileDrawerOpen(false);
  }, [location.pathname]);

  useEffect(() => {
    if (!mobileDrawerOpen) {
      return undefined;
    }

    const focusableSelector = [
      'a[href]',
      'button:not([disabled])',
      'input:not([disabled])',
      'select:not([disabled])',
      'textarea:not([disabled])',
      '[tabindex]:not([tabindex="-1"])',
    ].join(',');

    drawerRef.current?.focus();

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        closeMobileDrawer();
        return;
      }

      if (event.key !== 'Tab') {
        return;
      }

      const focusable = Array.from(
        drawerRef.current?.querySelectorAll<HTMLElement>(focusableSelector) ?? [],
      ).filter((element) => element.offsetParent !== null);

      if (focusable.length === 0) {
        event.preventDefault();
        drawerRef.current?.focus();
        return;
      }

      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };

    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, [mobileDrawerOpen]);

  useEffect(() => {
    if (mobileDrawerOpen) {
      return;
    }

    returnFocusRef.current?.focus();
    returnFocusRef.current = null;
  }, [mobileDrawerOpen]);

  return (
    <div className="min-h-screen bg-background">
      {/* Desktop fixed sidebar — md+ only */}
      <div className="hidden md:block">
        <CaringPanelSidebar
          collapsed={sidebarCollapsed}
          onToggle={() => setSidebarCollapsed((prev) => !prev)}
        />
      </div>

      <CaringPanelHeader
        sidebarCollapsed={sidebarCollapsed}
        onSidebarToggle={toggleMobileDrawer}
      />

      {mobileDrawerOpen && (
        <>
          <button
            type="button"
            aria-label={t('panel.navigation.close')}
            className="fixed inset-0 z-30 bg-black/50 md:hidden"
            onClick={closeMobileDrawer}
          />
          <div
            ref={drawerRef}
            role="dialog"
            aria-modal="true"
            aria-label={t('panel.navigation.label')}
            tabIndex={-1}
            className="fixed left-0 top-0 z-40 h-screen w-64 border-r border-divider bg-content1 transition-transform duration-300 md:hidden"
          >
            <CaringPanelSidebar
              collapsed={false}
              onToggle={closeMobileDrawer}
            />
          </div>
        </>
      )}

      <main
        className={`min-h-screen pt-16 transition-all duration-300 ${
          sidebarCollapsed ? 'md:ml-16' : 'md:ml-64'
        }`}
      >
        <div className="mx-auto w-full max-w-[96rem] px-3 py-4 sm:px-4 md:px-6">
          <CaringPanelBreadcrumbs />
          <Outlet />
        </div>
      </main>
    </div>
  );
}

export default CaringLayout;
