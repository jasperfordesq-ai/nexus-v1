// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Header Bar
 * Top bar with breadcrumbs, search, and user menu
 */

import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAuth, useTenant } from '@/contexts';

import ArrowLeft from 'lucide-react/icons/arrow-left';
import Bell from 'lucide-react/icons/bell';
import LogOut from 'lucide-react/icons/log-out';
import Menu from 'lucide-react/icons/menu';
import User from 'lucide-react/icons/user';
import { resolveAvatarUrl } from '@/lib/helpers';

import { Dropdown, DropdownTrigger, DropdownMenu, DropdownItem, Button, Avatar } from '@/components/ui';
interface AdminHeaderProps {
  sidebarCollapsed: boolean;
  onSidebarToggle?: () => void;
}

export function AdminHeader({ sidebarCollapsed, onSidebarToggle }: AdminHeaderProps) {
  const { t } = useTranslation('admin_nav');
  const { user, logout } = useAuth();
  const { tenantPath, tenant } = useTenant();
  const navigate = useNavigate();
  const adminLabel = t('admin');

  return (
    <header
      className={`fixed top-0 right-0 z-30 flex h-16 items-center justify-between gap-2 border-b border-divider/70 bg-surface/90 px-3 shadow-sm shadow-black/[0.03] backdrop-blur-xl sm:px-6 transition-all duration-300 left-0 ${
        sidebarCollapsed ? 'md:left-16' : 'md:left-64'
      }`}
    >
      {/* Left: Mobile menu + Back to frontend + tenant name */}
      <div className="flex min-w-0 items-center gap-2 sm:gap-4">
        {/* Mobile hamburger */}
        {onSidebarToggle && (
          <Button
            isIconOnly
            variant="tertiary"
            size="sm"
            onPress={onSidebarToggle}
            className="text-muted md:hidden"
            aria-label={t('toggle_sidebar')}
          >
            <Menu size={20} />
          </Button>
        )}
        <Button
          variant="tertiary"
          size="sm"
          onPress={() => navigate(tenantPath('/dashboard'))}
          startContent={<ArrowLeft size={16} aria-hidden="true" />}
          aria-label={t('back_to_site')}
          className="min-w-0 bg-surface-secondary/70 px-2 text-muted hover:bg-surface-tertiary/70 sm:px-3"
        >
          <span className="hidden sm:inline" aria-hidden="true">{t('back_to_site_header')}</span>
        </Button>
        {tenant?.name && (
          <span className="min-w-0 max-w-[9rem] truncate text-sm font-medium text-muted sm:max-w-[18rem]">
            {tenant.name}
          </span>
        )}
      </div>

      {/* Right: User menu */}
      <div className="flex shrink-0 items-center gap-2 sm:gap-3">
        <Button
          isIconOnly
          variant="tertiary"
          size="sm"
          onPress={() => navigate(tenantPath('/notifications'))}
          aria-label={t('notifications')}
          className="bg-surface-secondary/70 text-muted hover:bg-surface-tertiary/70"
        >
          <Bell size={18} />
        </Button>

        <Dropdown placement="bottom-end">
          <DropdownTrigger>
            <Button variant="tertiary" className="flex min-h-10 min-w-0 items-center gap-2 bg-surface-secondary/70 px-2 py-1 hover:bg-surface-tertiary/70">
              <Avatar
                src={resolveAvatarUrl(user?.avatar_url || user?.avatar) || undefined}
                name={user?.name || adminLabel}
                size="sm"
                className="h-8 w-8 ring-2 ring-surface"
              />
              <span className="hidden max-w-[10rem] truncate text-sm font-medium text-foreground sm:block">
                {user?.name || adminLabel}
              </span>
            </Button>
          </DropdownTrigger>
          <DropdownMenu
            aria-label={t('admin_menu')}
            onAction={(key) => {
              if (key === 'profile') navigate(tenantPath('/profile'));
              if (key === 'logout') logout();
            }}
          >
            <DropdownItem
              key="profile" id="profile"
              startContent={<User size={16} />}
            >
              {t('my_profile')}
            </DropdownItem>
            <DropdownItem
              key="logout" id="logout"
              startContent={<LogOut size={16} />}
              className="text-danger"
            >
              {t('sign_out')}
            </DropdownItem>
          </DropdownMenu>
        </Dropdown>
      </div>
    </header>
  );
}

export default AdminHeader;
