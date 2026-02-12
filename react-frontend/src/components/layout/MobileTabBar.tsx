/**
 * Mobile Bottom Tab Bar
 * Fixed navigation bar at the bottom of the screen on mobile devices
 * Hidden on md+ screens. Shows 5 tabs: Home, Listings, Create, Messages, Menu
 * Only visible when the user is authenticated and not on auth pages
 */

import { useState, useCallback } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
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
const hiddenRoutes = ['/login', '/register', '/forgot-password', '/reset-password'];

export function MobileTabBar({ onMenuOpen }: MobileTabBarProps) {
  const location = useLocation();
  const navigate = useNavigate();
  const { isAuthenticated } = useAuth();
  const { hasModule, tenantPath } = useTenant();
  const { counts } = useNotifications();

  const [isCreateOpen, setIsCreateOpen] = useState(false);

  const handleCreateOpen = useCallback(() => setIsCreateOpen(true), []);
  const handleCreateClose = useCallback(() => setIsCreateOpen(false), []);

  // Don't render for unauthenticated users or on auth pages
  if (!isAuthenticated) return null;
  if (hiddenRoutes.some((route) => location.pathname.startsWith(route))) return null;

  const currentPath = location.pathname;

  /** Check if a path is active (exact for /, prefix for others) */
  const isActive = (path: string) => {
    if (path === '/dashboard') {
      return currentPath === '/dashboard' || currentPath === '/';
    }
    return currentPath.startsWith(path);
  };

  // Build tabs dynamically based on enabled modules
  const tabs: {
    key: string;
    label: string;
    icon: React.ComponentType<{ className?: string }>;
    path?: string;
    action?: () => void;
    isCreate?: boolean;
    badgeCount?: number;
    module?: string;
  }[] = [
    {
      key: 'home',
      label: 'Home',
      icon: House,
      path: tenantPath('/dashboard'),
      module: 'dashboard',
    },
    {
      key: 'listings',
      label: 'Listings',
      icon: ListTodo,
      path: tenantPath('/listings'),
      module: 'listings',
    },
    {
      key: 'create',
      label: 'Create',
      icon: Plus,
      action: handleCreateOpen,
      isCreate: true,
    },
    {
      key: 'messages',
      label: 'Messages',
      icon: MessageSquare,
      path: tenantPath('/messages'),
      badgeCount: counts.messages,
      module: 'messages',
    },
    {
      key: 'menu',
      label: 'Menu',
      icon: Menu,
      action: onMenuOpen,
    },
  ];

  // Filter out tabs whose modules are disabled (but always show Create and Menu)
  const visibleTabs = tabs.filter((tab) => {
    if (!tab.module) return true;
    return hasModule(tab.module as never);
  });

  return (
    <>
      {/* Spacer to prevent content from being hidden behind the tab bar */}
      <div className="h-16 md:hidden" aria-hidden="true" />

      {/* Tab Bar */}
      <nav
        className="fixed bottom-0 left-0 right-0 z-50 md:hidden"
        aria-label="Mobile navigation"
        style={{ paddingBottom: 'env(safe-area-inset-bottom, 0px)' }}
      >
        <div className="bg-white/80 dark:bg-gray-900/80 backdrop-blur-xl border-t border-gray-200 dark:border-white/10">
          <div className="flex items-center justify-around px-2 h-16">
            {visibleTabs.map((tab) => {
              const Icon = tab.icon;
              const active = tab.path ? isActive(tab.path) : false;

              // Create button - prominent gradient FAB
              if (tab.isCreate) {
                return (
                  <div key={tab.key} className="flex flex-col items-center justify-center -mt-4">
                    <Button
                      isIconOnly
                      onPress={tab.action}
                      className="w-12 h-12 rounded-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/30 hover:shadow-indigo-500/50 transition-shadow"
                      aria-label="Create new content"
                    >
                      <AnimatePresence mode="wait">
                        <motion.div
                          key={isCreateOpen ? 'open' : 'closed'}
                          initial={{ rotate: 0 }}
                          animate={{ rotate: isCreateOpen ? 45 : 0 }}
                          transition={{ duration: 0.2 }}
                        >
                          <Plus className="w-6 h-6" aria-hidden="true" />
                        </motion.div>
                      </AnimatePresence>
                    </Button>
                    <span className="text-[10px] mt-1 text-theme-subtle font-medium">
                      {tab.label}
                    </span>
                  </div>
                );
              }

              // Regular tab — with optional badge
              const tabContent = (
                <Button
                  key={tab.key}
                  variant="light"
                  onPress={() => {
                    if (tab.action) {
                      tab.action();
                    } else if (tab.path) {
                      navigate(tab.path);
                    }
                  }}
                  className={`flex flex-col items-center justify-center gap-0.5 min-w-0 h-auto py-1.5 px-1 ${
                    active
                      ? 'text-indigo-600 dark:text-indigo-400'
                      : 'text-theme-muted'
                  }`}
                  aria-label={tab.label}
                  aria-current={active ? 'page' : undefined}
                >
                  <div className="relative">
                    <Icon
                      className={`w-5 h-5 ${
                        active ? 'text-indigo-600 dark:text-indigo-400' : ''
                      }`}
                      aria-hidden="true"
                    />
                    {/* Active indicator dot */}
                    {active && (
                      <motion.div
                        layoutId="tab-indicator"
                        className="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-indigo-600 dark:bg-indigo-400"
                        transition={{ type: 'spring', stiffness: 500, damping: 30 }}
                      />
                    )}
                  </div>
                  <span
                    className={`text-[10px] font-medium leading-none ${
                      active
                        ? 'text-indigo-600 dark:text-indigo-400'
                        : 'text-theme-subtle'
                    }`}
                  >
                    {tab.label}
                  </span>
                </Button>
              );

              // Wrap in HeroUI Badge if tab has unread count
              if (tab.badgeCount && tab.badgeCount > 0) {
                return (
                  <Badge
                    key={tab.key}
                    content={tab.badgeCount > 99 ? '99+' : tab.badgeCount}
                    color="danger"
                    size="sm"
                    placement="top-right"
                    className="translate-x-1 -translate-y-0.5"
                  >
                    {tabContent}
                  </Badge>
                );
              }

              return tabContent;
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
