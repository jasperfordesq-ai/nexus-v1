// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Mobile Navigation Drawer
 * Uses HeroUI Drawer component for accessibility and animations
 * Theme-aware styling for light and dark modes
 */

import { useEffect, useRef } from 'react';
import { Link, NavLink, useNavigate, useLocation } from 'react-router-dom';
import {
  Button,
  Avatar,
  Divider,
  Drawer,
  DrawerContent,
  DrawerHeader,
  DrawerBody,
} from '@heroui/react';
import {
  X,
  Home,
  LayoutDashboard,
  ListTodo,
  MessageSquare,
  Wallet,
  Users,
  Calendar,
  Settings,
  LogOut,
  HelpCircle,
  Trophy,
  Medal,
  Target,
  Hexagon,
  ArrowRightLeft,
  Newspaper,
  BookOpen,
  FolderOpen,
  Heart,
  Building2,
  Search,
  Shield,
  Globe,
  Info,
  FileText,
  Handshake,
  Stethoscope,
  TrendingUp,
  BarChart3,
  Compass,
  Cookie,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useAuth, useTenant, useNotifications, useCookieConsent } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';
import { tokenManager, API_BASE } from '@/lib/api';
import type { TenantFeatures, TenantModules } from '@/types/api';
import { LanguageSwitcher } from '@/components/LanguageSwitcher';
import { useMenuContext } from '@/contexts';
import { MobileMenuItems } from '@/components/navigation';

interface MobileDrawerProps {
  isOpen: boolean;
  onClose: () => void;
  onSearchOpen?: () => void;
}

export function MobileDrawer({ isOpen, onClose, onSearchOpen }: MobileDrawerProps) {
  const navigate = useNavigate();
  const location = useLocation();
  const { t } = useTranslation('common');
  const { user, isAuthenticated, logout } = useAuth();
  const { tenant, branding, hasFeature, hasModule, tenantPath } = useTenant();
  const { unreadCount, counts } = useNotifications();
  const { resetConsent } = useCookieConsent();
  const { mobileMenus, headerMenus, hasCustomMenus } = useMenuContext();

  // Use mobile-specific menus if available, fall back to header menus
  const apiMenus = mobileMenus.length > 0 ? mobileMenus : headerMenus;

  // Nav item arrays — defined inside component so t() is available
  const mainNavItems = [
    { label: t('nav.home'), href: '/', icon: Home },
    { label: t('nav.dashboard'), href: '/dashboard', icon: LayoutDashboard, auth: true, module: 'dashboard' as keyof TenantModules },
    { label: t('nav.feed'), href: '/feed', icon: Newspaper, auth: true, module: 'feed' as keyof TenantModules },
    { label: t('nav.listings'), href: '/listings', icon: ListTodo, module: 'listings' as keyof TenantModules },
    { label: t('nav.messages'), href: '/messages', icon: MessageSquare, auth: true, module: 'messages' as keyof TenantModules },
    { label: t('nav.wallet'), href: '/wallet', icon: Wallet, auth: true, module: 'wallet' as keyof TenantModules },
  ];

  const communityNavItems = [
    { label: t('nav.exchanges'), href: '/exchanges', icon: ArrowRightLeft, feature: 'exchange_workflow' as const },
    { label: t('nav.group_exchanges'), href: '/group-exchanges', icon: Users, feature: 'group_exchanges' as keyof TenantFeatures },
    { label: t('nav.members'), href: '/members', icon: Users, feature: 'connections' as const },
    { label: t('nav.events'), href: '/events', icon: Calendar, feature: 'events' as const },
    { label: t('nav.groups'), href: '/groups', icon: Users, feature: 'groups' as const },
    { label: t('nav.blog'), href: '/blog', icon: BookOpen, feature: 'blog' as const },
    { label: t('nav.volunteering'), href: '/volunteering', icon: Heart, feature: 'volunteering' as const },
    { label: t('nav.organisations'), href: '/organisations', icon: Building2, feature: 'organisations' as const },
    { label: t('nav.resources'), href: '/resources', icon: FolderOpen, feature: 'resources' as const },
  ];

  const exploreNavItems = [
    { label: t('nav.achievements'), href: '/achievements', icon: Trophy, feature: 'gamification' as const },
    { label: t('nav.leaderboard'), href: '/leaderboard', icon: Medal, feature: 'gamification' as const },
    { label: t('nav.goals'), href: '/goals', icon: Target, feature: 'goals' as const },
  ];

  const federationNavItems = [
    { label: t('nav.federation_hub'), href: '/federation', icon: Globe, feature: 'federation' as keyof TenantFeatures },
    { label: t('nav.partner_communities'), href: '/federation/partners', icon: Building2, feature: 'federation' as keyof TenantFeatures },
    { label: t('nav.federated_members'), href: '/federation/members', icon: Users, feature: 'federation' as keyof TenantFeatures },
    { label: t('nav.federated_messages'), href: '/federation/messages', icon: MessageSquare, feature: 'federation' as keyof TenantFeatures },
    { label: t('nav.federated_listings'), href: '/federation/listings', icon: ListTodo, feature: 'federation' as keyof TenantFeatures },
    { label: t('nav.federated_events'), href: '/federation/events', icon: Calendar, feature: 'federation' as keyof TenantFeatures },
  ];

  // Universal about items — shown for all tenants
  const aboutNavItems = [
    { label: t('nav.about'), href: '/about', icon: Info },
    { label: t('nav.faq'), href: '/faq', icon: HelpCircle },
    { label: t('nav.timebanking_guide'), href: '/timebanking-guide', icon: BookOpen },
  ];

  // Tenant 2 (hOUR Timebank) specific pages — contain hardcoded org content
  const hourTimebankAboutItems = [
    { label: t('nav.partner_with_us'), href: '/partner', icon: Handshake },
    { label: t('nav.social_prescribing'), href: '/social-prescribing', icon: Stethoscope },
    { label: t('nav.our_impact'), href: '/impact-summary', icon: TrendingUp },
    { label: t('nav.impact_report'), href: '/impact-report', icon: BarChart3 },
    { label: t('nav.strategic_plan'), href: '/strategic-plan', icon: Compass },
  ];

  const supportNavItems = [
    { label: t('support.help_center'), href: '/help', icon: HelpCircle },
    { label: t('support.contact'), href: '/contact', icon: MessageSquare },
  ];

  const legalNavItems = [
    { label: t('legal.legal_hub'), href: '/legal', icon: FileText },
    { label: t('legal.terms_of_service'), href: '/terms', icon: FileText },
    { label: t('legal.privacy_policy'), href: '/privacy', icon: FileText },
    { label: t('legal.cookie_policy', 'Cookie Policy'), href: '/cookies', icon: Cookie },
    { label: t('legal.accessibility'), href: '/accessibility', icon: FileText },
  ];

  // Track previous pathname to only close on actual navigation
  const prevPathRef = useRef(location.pathname);

  // Close on route change (but not on initial mount)
  useEffect(() => {
    if (prevPathRef.current !== location.pathname) {
      onClose();
      prevPathRef.current = location.pathname;
    }
  }, [location.pathname, onClose]);

  const handleLogout = async () => {
    await logout();
    onClose();
    navigate(tenantPath('/login'));
  };

  const renderNavLink = (item: {
    label: string;
    href: string;
    icon: React.ComponentType<{ className?: string }>;
    auth?: boolean;
    feature?: keyof TenantFeatures;
    module?: keyof TenantModules;
  }) => {
    // Check feature flag
    if (item.feature && !hasFeature(item.feature)) {
      return null;
    }

    // Check module flag
    if (item.module && !hasModule(item.module)) {
      return null;
    }

    // Check auth requirement
    if (item.auth && !isAuthenticated) {
      return null;
    }

    const Icon = item.icon;
    const resolvedHref = tenantPath(item.href);

    return (
      <NavLink
        key={item.href}
        to={resolvedHref}
        className={({ isActive }) =>
          `flex items-center gap-3 px-4 py-3 rounded-xl text-base font-medium transition-all ${
            isActive
              ? 'bg-theme-active text-theme-primary'
              : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover'
          }`
        }
      >
        <Icon className="w-5 h-5" aria-hidden="true" />
        <span>{item.label}</span>
      </NavLink>
    );
  };

  return (
    <Drawer
      isOpen={isOpen}
      onClose={onClose}
      placement="right"
      size="sm"
      hideCloseButton
      classNames={{
        base: 'bg-[var(--surface-overlay)] border-l border-[var(--border-default)] shadow-2xl',
        header: 'border-b border-[var(--border-default)] p-4',
        body: 'p-0',
      }}
    >
      <DrawerContent>
        {/* Header */}
        <DrawerHeader className="flex items-center justify-between">
          <Link to={tenantPath('/')} className="flex items-center gap-2">
            {branding.logo ? (
              <img src={branding.logo} alt={branding.name} className="h-9 w-auto object-contain" />
            ) : (
              <Hexagon className="w-8 h-8 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
            )}
            <span className="font-bold text-xl text-gradient">{branding.name}</span>
          </Link>
          <Button
            isIconOnly
            variant="light"
            className="text-theme-muted hover:text-theme-primary"
            onPress={onClose}
            aria-label="Close menu"
          >
            <X className="w-6 h-6" aria-hidden="true" />
          </Button>
        </DrawerHeader>

        <DrawerBody>
          {/* Search Button */}
          {onSearchOpen && (
            <div className="px-4 pt-3 pb-1">
              <Button
                variant="flat"
                fullWidth
                className="flex items-center justify-start gap-3 px-4 py-2.5 rounded-xl bg-theme-elevated hover:bg-theme-hover border border-theme-default text-sm text-theme-subtle h-auto"
                onPress={() => { onClose(); onSearchOpen(); }}
                aria-label="Open search"
              >
                <Search className="w-4 h-4" aria-hidden="true" />
                <span>{t('search.placeholder')}</span>
              </Button>
            </div>
          )}

          {/* User Section */}
          {isAuthenticated && user && (
            <div className="p-4 border-b border-[var(--border-default)]">
              <Link
                to={tenantPath('/profile')}
                className="flex items-center gap-3"
              >
                <Avatar
                  name={`${user.first_name} ${user.last_name}`}
                  src={resolveAvatarUrl(user.avatar_url || user.avatar)}
                  size="lg"
                  showFallback
                />
                <div>
                  <p className="font-semibold text-theme-primary">
                    {user.first_name} {user.last_name}
                  </p>
                  <p className="text-sm text-theme-subtle">{user.email}</p>
                </div>
              </Link>

              {/* Quick Stats */}
              <div className="grid grid-cols-3 gap-2 sm:gap-4 mt-4">
                <Link
                  to={tenantPath('/wallet')}
                  className="text-center p-2 rounded-xl bg-theme-elevated hover:bg-theme-hover transition-colors"
                >
                  <p className="text-lg font-bold text-theme-primary">
                    {user.balance ?? 0}
                  </p>
                  <p className="text-xs text-theme-subtle">{t('stats.credits')}</p>
                </Link>
                <Link
                  to={tenantPath('/messages')}
                  className="text-center p-2 rounded-xl bg-theme-elevated hover:bg-theme-hover transition-colors relative"
                >
                  <p className="text-lg font-bold text-theme-primary">
                    {counts.messages > 0 ? counts.messages : 0}
                  </p>
                  <p className="text-xs text-theme-subtle">{t('stats.messages')}</p>
                  {counts.messages > 0 && (
                    <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full" aria-hidden="true" />
                  )}
                </Link>
                <Link
                  to={tenantPath('/notifications')}
                  className="text-center p-2 rounded-xl bg-theme-elevated hover:bg-theme-hover transition-colors relative"
                >
                  <p className="text-lg font-bold text-theme-primary">
                    {unreadCount > 0 ? unreadCount : 0}
                  </p>
                  <p className="text-xs text-theme-subtle">{t('stats.alerts')}</p>
                  {unreadCount > 0 && (
                    <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full" aria-hidden="true" />
                  )}
                </Link>
              </div>
            </div>
          )}

          {/* Navigation */}
          <nav className="p-4 space-y-6" aria-label="Mobile navigation">
            {hasCustomMenus ? (
              /* API-driven navigation when custom menus exist */
              <div className="space-y-1">
                <MobileMenuItems menus={apiMenus} />
              </div>
            ) : (
            <>
            {/* Main */}
            <div className="space-y-1">
              {mainNavItems.map(renderNavLink)}
            </div>

            {/* Community */}
            {communityNavItems.filter(item => !item.feature || hasFeature(item.feature)).length > 0 && (
              <div>
                <p className="px-4 mb-2 text-xs font-semibold text-theme-subtle uppercase tracking-wider">
                  {t('sections.community')}
                </p>
                <div className="space-y-1">
                  {communityNavItems.map(renderNavLink)}
                </div>
              </div>
            )}

            {/* Explore */}
            {(hasFeature('gamification') || hasFeature('goals')) && (
              <div>
                <p className="px-4 mb-2 text-xs font-semibold text-theme-subtle uppercase tracking-wider">
                  {t('sections.explore')}
                </p>
                <div className="space-y-1">
                  {exploreNavItems.map(renderNavLink)}
                </div>
              </div>
            )}

            {/* Federation */}
            {hasFeature('federation') && isAuthenticated && (
              <div>
                <p className="px-4 mb-2 text-xs font-semibold text-theme-subtle uppercase tracking-wider flex items-center gap-2">
                  <Globe className="w-3 h-3" aria-hidden="true" />
                  {t('sections.federation')}
                </p>
                <div className="space-y-1">
                  {federationNavItems.map(renderNavLink)}
                </div>
              </div>
            )}

            {/* About */}
            <div>
              <p className="px-4 mb-2 text-xs font-semibold text-theme-subtle uppercase tracking-wider">
                {t('sections.about')}
              </p>
              <div className="space-y-1">
                {aboutNavItems.map(renderNavLink)}
                {tenant?.slug === 'hour-timebank' && hourTimebankAboutItems.map(renderNavLink)}
                {(tenant?.menu_pages?.about || []).map((p: { title: string; slug: string }) => renderNavLink({
                  label: p.title,
                  href: `/page/${p.slug}`,
                  icon: FileText,
                }))}
              </div>
            </div>
            </>
            )}

            {/* Support — always hardcoded */}
            <div>
              <p className="px-4 mb-2 text-xs font-semibold text-theme-subtle uppercase tracking-wider">
                {t('sections.support')}
              </p>
              <div className="space-y-1">
                {supportNavItems.map(renderNavLink)}
              </div>
            </div>

            {/* Legal */}
            <div>
              <p className="px-4 mb-2 text-xs font-semibold text-theme-subtle uppercase tracking-wider">
                {t('sections.legal')}
              </p>
              <div className="space-y-1">
                {legalNavItems.map(renderNavLink)}
                <button
                  onClick={() => { resetConsent(); onClose(); }}
                  className="flex items-center gap-3 px-4 py-3 rounded-xl text-base font-medium text-theme-muted hover:text-theme-primary hover:bg-theme-hover transition-all w-full text-left"
                >
                  <Settings className="w-5 h-5" aria-hidden="true" />
                  <span>{t('cookie_consent.manage', 'Cookie Settings')}</span>
                </button>
              </div>
            </div>

            {/* Admin Tools */}
            {isAuthenticated && user && (user.role === 'admin' || user.role === 'tenant_admin' || user.role === 'super_admin' || user.is_admin || user.is_super_admin) && (
              <div>
                <Divider className="bg-theme-elevated mb-4" />
                <p className="px-4 mb-2 text-xs font-semibold text-theme-subtle uppercase tracking-wider flex items-center gap-2">
                  <Shield className="w-3 h-3" aria-hidden="true" />
                  {t('admin_tools.section')}
                </p>
                <div className="space-y-1">
                  <a
                    href="/admin"
                    className="flex items-center gap-3 px-4 py-3 rounded-xl text-base font-medium text-theme-muted hover:text-theme-primary hover:bg-theme-hover transition-all"
                  >
                    <LayoutDashboard className="w-5 h-5" aria-hidden="true" />
                    <span>{t('admin_tools.admin_panel')}</span>
                  </a>
                  <a
                    href={(API_BASE.startsWith('http') ? new URL(API_BASE).origin : window.location.origin) + '/admin-legacy'}
                    onClick={(e) => { e.preventDefault(); const token = tokenManager.getAccessToken(); const phpOrigin = API_BASE.startsWith('http') ? new URL(API_BASE).origin : window.location.origin; if (token) { const f = document.createElement('form'); f.method = 'POST'; f.action = `${phpOrigin}/api/auth/admin-session`; f.style.display = 'none'; const ti = document.createElement('input'); ti.name = 'token'; ti.value = token; f.appendChild(ti); const ri = document.createElement('input'); ri.name = 'redirect'; ri.value = '/admin-legacy'; f.appendChild(ri); document.body.appendChild(f); f.submit(); } else { window.location.href = `${phpOrigin}/admin-legacy`; } }}
                    className="flex items-center gap-3 px-4 py-3 rounded-xl text-base font-medium text-theme-muted hover:text-theme-primary hover:bg-theme-hover transition-all"
                  >
                    <Shield className="w-5 h-5" aria-hidden="true" />
                    <span>{t('admin_tools.legacy_admin')}</span>
                  </a>
                </div>
              </div>
            )}

            {/* Account */}
            {isAuthenticated && (
              <div>
                <p className="px-4 mb-2 text-xs font-semibold text-theme-subtle uppercase tracking-wider">
                  {t('sections.account')}
                </p>
                <div className="space-y-1">
                  <NavLink
                    to={tenantPath('/settings')}
                    className={({ isActive }) =>
                      `flex items-center gap-3 px-4 py-3 rounded-xl text-base font-medium transition-all ${
                        isActive
                          ? 'bg-theme-active text-theme-primary'
                          : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover'
                      }`
                    }
                  >
                    <Settings className="w-5 h-5" aria-hidden="true" />
                    <span>{t('account.settings')}</span>
                  </NavLink>
                  <div className="flex items-center gap-3 px-4 py-2">
                    <Globe className="w-5 h-5 text-theme-muted shrink-0" aria-hidden="true" />
                    <span className="text-base font-medium text-theme-muted">{t('sections.language')}</span>
                    <div className="ml-auto">
                      <LanguageSwitcher compact={false} />
                    </div>
                  </div>
                  <Button
                    variant="light"
                    onPress={handleLogout}
                    className="flex items-center justify-start gap-3 px-4 py-3 rounded-xl text-base font-medium text-red-500 dark:text-red-400 hover:bg-red-500/10 transition-all w-full h-auto"
                  >
                    <LogOut className="w-5 h-5" aria-hidden="true" />
                    <span>{t('account.log_out')}</span>
                  </Button>
                </div>
              </div>
            )}

            {/* Auth buttons for guests */}
            {!isAuthenticated && (
              <div className="space-y-2 pt-4">
                <Divider className="bg-theme-elevated" />
                <Link to={tenantPath('/login')}>
                  <Button
                    variant="flat"
                    className="w-full bg-theme-elevated text-theme-secondary"
                  >
                    {t('auth.log_in')}
                  </Button>
                </Link>
                <Link to={tenantPath('/register')}>
                  <Button className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium">
                    {t('auth.sign_up')}
                  </Button>
                </Link>
              </div>
            )}

            {/* Attribution (AGPL Section 7(b) — required on all pages) */}
            <div className="pt-6 pb-4 px-4">
              <Divider className="bg-theme-elevated mb-4" />
              <a
                href="https://github.com/jasperfordesq-ai/nexus-v1"
                target="_blank"
                rel="noopener noreferrer"
                className="block text-center text-xs text-theme-subtle hover:text-theme-primary transition-colors"
              >
                Built on Project NEXUS by Jasper Ford
              </a>
            </div>
          </nav>
        </DrawerBody>
      </DrawerContent>
    </Drawer>
  );
}

export default MobileDrawer;
