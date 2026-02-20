// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Mobile Bottom Tab Bar
 * Fixed navigation bar at the bottom of the screen on mobile devices.
 * Hidden on md+ screens. Shows up to 5 tabs: Home, Listings, Create, Messages, Menu.
 * Only visible when the user is authenticated and not on auth pages.
 */

import React, { useState, useCallback } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Badge, Button } from '@heroui/react';
import {
  House,
  ListTodo,
  Plus,
  MessageSquare,
  Menu,
} from 'lucide-react';
import { useAuth, useTenant, useNotifications } from '@/contexts';
import { QuickCreateMenu } from './QuickCreateMenu';

interface MobileTabBarProps {
  onMenuOpen?: () => void;
}

/** Routes where the tab bar should be hidden */
const hiddenRoutes = ['/login', '/register', '/forgot-password', '/reset-password', '/onboarding'];

export function MobileTabBar({ onMenuOpen }: MobileTabBarProps) {
  const location = useLocation();
  const navigate = useNavigate();
  const { isAuthenticated } = useAuth();
  const { hasModule, tenantPath } = useTenant();
  const { counts } = useNotifications();

  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const handleCreateOpen = useCallback(() => setIsCreateOpen(true), []);
  const handleCreateClose = useCallback(() => setIsCreateOpen(false), []);

  // Don't render for unauthenticated users or on auth/onboarding pages
  if (!isAuthenticated) return null;
  if (hiddenRoutes.some((route) => location.pathname.includes(route))) return null;

  const currentPath = location.pathname;

  const isActive = (path: string) => {
    if (path.endsWith('/dashboard')) {
      return currentPath.endsWith('/dashboard') || currentPath === '/' || currentPath.match(/^\/[^/]+\/?$/);
    }
    return currentPath.includes(path.replace(tenantPath(''), ''));
  };

  // Build tabs dynamically based on enabled modules
  const tabs: {
    key: string;
    label: string;
    icon: React.ComponentType<{ className?: string; strokeWidth?: string | number }>;
    path?: string;
    action?: () => void;
    isCreate?: boolean;
    badgeCount?: number;
    module?: string;
  }[] = [
    { key: 'home',     label: 'Home',     icon: House,         path: tenantPath('/dashboard'), module: 'dashboard' },
    { key: 'listings', label: 'Listings', icon: ListTodo,      path: tenantPath('/listings'),  module: 'listings' },
    { key: 'create',   label: 'Create',   icon: Plus,          action: handleCreateOpen,       isCreate: true },
    { key: 'messages', label: 'Messages', icon: MessageSquare, path: tenantPath('/messages'),  badgeCount: counts.messages, module: 'messages' },
    { key: 'menu',     label: 'Menu',     icon: Menu,          action: onMenuOpen },
  ];

  const visibleTabs = tabs.filter((tab) => {
    if (!tab.module) return true;
    return hasModule(tab.module as never);
  });

  return (
    <>
      {/* Spacer so page content isn't hidden behind the fixed bar */}
      <div className="h-16 md:hidden" aria-hidden="true" />

      <nav
        className="fixed bottom-0 left-0 right-0 z-300 md:hidden"
        aria-label="Mobile navigation"
        style={{ paddingBottom: 'env(safe-area-inset-bottom, 0px)' }}
      >
        {/* Glass surface */}
        <div className="bg-[var(--glass-bg)] backdrop-blur-xl border-t border-[var(--border-default)] shadow-[0_-4px_24px_rgba(0,0,0,0.08)]">
          <div className="flex items-stretch h-16 px-1">
            {visibleTabs.map((tab) => {
              const Icon = tab.icon;
              const active = tab.path ? isActive(tab.path) : false;

              /* ── Create FAB ────────────────────────────── */
              if (tab.isCreate) {
                return (
                  <div
                    key={tab.key}
                    className="flex flex-col items-center justify-center flex-1 -mt-3"
                  >
                    <Button
                      isIconOnly
                      radius="full"
                      onPress={tab.action}
                      className="w-13 h-13 min-w-0 bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/40 hover:shadow-indigo-500/60 hover:scale-105 active:scale-95 transition-all duration-200"
                      aria-label="Create new content"
                      style={{ width: '52px', height: '52px' }}
                    >
                      <motion.div
                        animate={{ rotate: isCreateOpen ? 45 : 0 }}
                        transition={{ type: 'spring', stiffness: 400, damping: 25 }}
                      >
                        <Plus className="w-6 h-6" strokeWidth={2.5} aria-hidden="true" />
                      </motion.div>
                    </Button>
                    <span className="text-[10px] mt-0.5 font-medium text-theme-subtle leading-none">
                      {tab.label}
                    </span>
                  </div>
                );
              }

              /* ── Regular tab ───────────────────────────── */
              const hasBadge = (tab.badgeCount ?? 0) > 0;

              const tabButton = (
                <Button
                  variant="light"
                  radius="lg"
                  onPress={() => {
                    if (tab.action) tab.action();
                    else if (tab.path) navigate(tab.path);
                  }}
                  className={`
                    relative flex flex-col items-center justify-center flex-1 h-full gap-0.5
                    min-w-0 px-1 py-1.5 rounded-none
                    transition-colors duration-150
                    ${active
                      ? 'text-indigo-600 dark:text-indigo-400'
                      : 'text-theme-muted hover:text-theme-primary'
                    }
                  `}
                  aria-label={tab.label}
                  aria-current={active ? 'page' : undefined}
                >
                  {/* Active background pill — always rendered, opacity-driven */}
                  <div
                    className={`absolute inset-x-2 top-1.5 h-8 rounded-xl transition-opacity duration-200 ${
                      active
                        ? 'opacity-100 bg-indigo-500/10 dark:bg-indigo-400/10'
                        : 'opacity-0'
                    }`}
                  />

                  <div className="relative z-10 flex flex-col items-center gap-0.5">
                    <Icon
                      className={`w-5 h-5 transition-transform duration-150 ${active ? 'scale-110' : ''}`}
                      strokeWidth={active ? 2.5 : 2}
                      aria-hidden="true"
                    />
                    <span className="text-[10px] font-medium leading-none">{tab.label}</span>
                  </div>

                  {/* Active indicator dot — always rendered, opacity-driven */}
                  <div
                    className={`absolute bottom-1.5 w-1 h-1 rounded-full transition-opacity duration-200 ${
                      active
                        ? 'opacity-100 bg-indigo-600 dark:bg-indigo-400'
                        : 'opacity-0'
                    }`}
                  />
                </Button>
              );

              // Always wrap in Badge — use isInvisible to avoid DOM structure changes on count update
              if (tab.badgeCount !== undefined) {
                return (
                  <Badge
                    key={tab.key}
                    content={hasBadge ? (tab.badgeCount! > 99 ? '99+' : tab.badgeCount) : 0}
                    color="danger"
                    size="sm"
                    placement="top-right"
                    isInvisible={!hasBadge}
                    className="translate-x-[-8px] translate-y-[6px]"
                  >
                    {tabButton}
                  </Badge>
                );
              }

              return <React.Fragment key={tab.key}>{tabButton}</React.Fragment>;
            })}
          </div>
        </div>
      </nav>

      {/* Quick Create Menu Modal */}
      <QuickCreateMenu isOpen={isCreateOpen} onClose={handleCreateClose} />
    </>
  );
}

export default MobileTabBar;
