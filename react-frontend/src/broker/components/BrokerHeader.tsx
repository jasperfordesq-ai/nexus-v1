// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Header Bar
 * Simplified header with back-to-site, tenant name, and user menu.
 */

import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAuth, useTenant } from '@/contexts';
import {
  Button,
  Avatar,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
} from '@heroui/react';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import Bell from 'lucide-react/icons/bell';
import LogOut from 'lucide-react/icons/log-out';
import Menu from 'lucide-react/icons/menu';
import User from 'lucide-react/icons/user';
import { resolveAvatarUrl } from '@/lib/helpers';

interface BrokerHeaderProps {
  sidebarCollapsed: boolean;
  onSidebarToggle?: () => void;
}

export function BrokerHeader({ sidebarCollapsed, onSidebarToggle }: BrokerHeaderProps) {
  const { t } = useTranslation('broker');
  const { user, logout } = useAuth();
  const { tenantPath, tenant } = useTenant();
  const navigate = useNavigate();

  return (
    <header
      className={`fixed top-0 right-0 z-30 flex h-16 items-center justify-between gap-2 border-b border-divider bg-content1/95 backdrop-blur px-3 sm:px-6 transition-all duration-300 left-0 ${
        sidebarCollapsed ? 'md:left-16' : 'md:left-64'
      }`}
    >
      {/* Left: Mobile menu + Back to frontend + tenant name */}
      <div className="flex min-w-0 items-center gap-2 sm:gap-4">
        {onSidebarToggle && (
          <Button
            isIconOnly
            variant="light"
            size="sm"
            onPress={onSidebarToggle}
            className="text-default-500 md:hidden"
            aria-label={t('header.toggle_sidebar')}
          >
            <Menu size={20} />
          </Button>
        )}
        <Button
          variant="light"
          size="sm"
          onPress={() => navigate(tenantPath('/dashboard'))}
          startContent={<ArrowLeft size={16} />}
          className="text-default-500 min-w-0 px-2 sm:px-3"
        >
          <span className="hidden sm:inline">{t('header.back_to_site')}</span>
        </Button>
        {tenant?.name && (
          <span className="min-w-0 max-w-[9rem] truncate text-sm font-medium text-default-400 sm:max-w-[18rem]">
            {tenant.name}
          </span>
        )}
      </div>

      {/* Right: User menu */}
      <div className="flex shrink-0 items-center gap-2 sm:gap-3">
        <Button
          isIconOnly
          variant="light"
          size="sm"
          onPress={() => navigate(tenantPath('/notifications'))}
          aria-label={t('header.notifications')}
        >
          <Bell size={18} />
        </Button>

        <Dropdown placement="bottom-end">
          <DropdownTrigger>
            <Button variant="light" className="flex items-center gap-2 px-2 py-1 h-auto min-w-0">
              <Avatar
                src={resolveAvatarUrl(user?.avatar_url || user?.avatar) || undefined}
                name={user?.name || t('header.user_fallback')}
                size="sm"
                className="h-8 w-8"
              />
              <span className="hidden max-w-[10rem] truncate text-sm font-medium text-foreground sm:block">
                {user?.name || t('header.user_fallback')}
              </span>
            </Button>
          </DropdownTrigger>
          <DropdownMenu
            aria-label={t('header.user_menu')}
            onAction={(key) => {
              if (key === 'profile') navigate(tenantPath('/profile'));
              if (key === 'logout') logout();
            }}
          >
            <DropdownItem key="profile" startContent={<User size={16} />}>
              {t('header.my_profile')}
            </DropdownItem>
            <DropdownItem
              key="logout"
              startContent={<LogOut size={16} />}
              className="text-danger"
              color="danger"
            >
              {t('header.sign_out')}
            </DropdownItem>
          </DropdownMenu>
        </Dropdown>
      </div>
    </header>
  );
}

export default BrokerHeader;
