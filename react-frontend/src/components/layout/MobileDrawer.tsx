// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Mobile Navigation Drawer
 * Uses HeroUI Drawer component for accessibility and animations.
 * Sections are collapsible via HeroUI Accordion.
 * Admin/Help/Theme/Language consolidated into a utility row at the bottom.
 */

import { useEffect, useRef, useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import {
  Button,
  Avatar,
  Divider,
  Drawer,
  DrawerContent,
  DrawerHeader,
  DrawerBody,
  Accordion,
  AccordionItem,
} from '@heroui/react';
import {
  X,
  Home,
  LayoutDashboard,
  ListTodo,
  MessageSquare,
  Wallet,
  Users,
  Users2,
  Calendar,
  Settings,
  LogOut,
  HelpCircle,
  Trophy,
  Medal,
  Target,
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
  Bot,
  Briefcase,
  ShoppingBag,
  Lightbulb,
  GraduationCap,
  Activity,
  Sun,
  Moon,
  FlaskConical,
  Fingerprint,
  Bookmark,
} from 'lucide-react';
import { RELEASE_STATUS } from '@/config/releaseStatus';
import { TenantLogo } from '@/components/branding';
import { VerificationBadgeRow } from '@/components/verification/VerificationBadge';
import { useTranslation } from 'react-i18next';
import { useAuth, useTenant, useNotifications, useCookieConsent, useTheme } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';
import type { TenantFeatures, TenantModules } from '@/types/api';
import { LanguageSwitcher } from '@/components/LanguageSwitcher';
import { useMenuContext } from '@/contexts';
import { MobileMenuItems } from '@/components/navigation';

interface IdentityStatusResponse {
  has_id_verified_badge: boolean;
}

// Identity verification CTA for mobile menu — shows "Verify Identity" if not verified
function IdentityVerificationCTA({ userId, tenantPath, onClose }: { userId: number; tenantPath: (p: string) => string; onClose: () => void }) {
  const [isVerified, setIsVerified] = useState<boolean | null>(null);
  const navigate = useNavigate();
  const { t } = useTranslation('common');

  useEffect(() => {
    if (!userId) return;
    let cancelled = false;
    import('@/lib/api').then(({ api }) => {
      api.get<IdentityStatusResponse>('/v2/identity/status').then((res) => {
        if (!cancelled) setIsVerified(res?.data?.has_id_verified_badge === true);
      }).catch(() => { if (!cancelled) setIsVerified(false); });
    }).catch(() => { if (!cancelled) setIsVerified(false); });
    return () => { cancelled = true; };
  }, [userId]);

  if (isVerified === null) return null; // Loading
  if (isVerified) return null; // Already verified — VerificationBadgeRow handles display

  return (
    <Button
      variant="flat"
      onPress={() => { onClose(); setTimeout(() => navigate(tenantPath('/verify-identity-optional')), 150); }}
      className="mt-2 w-full flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-sm font-semibold hover:bg-emerald-500/20 h-auto"
    >
      <Fingerprint className="w-4 h-4" />
      {t('nav.verify_identity', 'Verify Your Identity')}
    </Button>
  );
}

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
  const { tenant, hasFeature, hasModule, tenantPath } = useTenant();
  const { unreadCount, counts } = useNotifications();
  const { resetConsent } = useCookieConsent();
  const { resolvedTheme, toggleTheme } = useTheme();
  const { mobileMenus, headerMenus, hasCustomMenus } = useMenuContext();

  const isAdmin = Boolean(user?.role === 'admin' || user?.role === 'tenant_admin' || user?.role === 'super_admin' || user?.is_admin || user?.is_super_admin || user?.is_tenant_super_admin);

  // Use mobile-specific menus if available, fall back to header menus
  const apiMenus = mobileMenus.length > 0 ? mobileMenus : headerMenus;

  // Track which accordion sections are expanded
  const [expandedKeys, setExpandedKeys] = useState<Set<string>>(new Set(['main']));

  // Nav item arrays
  const mainNavItems = [
    { label: t('nav.home'), href: '/', icon: Home },
    { label: t('nav.feed'), href: '/feed', icon: Newspaper, auth: true, module: 'feed' as keyof TenantModules },
    { label: t('nav.dashboard'), href: '/dashboard', icon: LayoutDashboard, auth: true, module: 'dashboard' as keyof TenantModules },
    { label: t('nav.explore', 'Explore'), href: '/explore', icon: Compass },
    { label: t('nav.messages'), href: '/messages', icon: MessageSquare, auth: true, module: 'messages' as keyof TenantModules },
  ];

  const timebankingNavItems = [
    { label: t('nav.listings'), href: '/listings', icon: ListTodo, module: 'listings' as keyof TenantModules },
    { label: t('nav.exchanges'), href: '/exchanges', icon: ArrowRightLeft, feature: 'exchange_workflow' as const },
    { label: t('nav.group_exchanges'), href: '/group-exchanges', icon: Users, feature: 'group_exchanges' as keyof TenantFeatures },
    { label: t('nav.wallet'), href: '/wallet', icon: Wallet, auth: true, module: 'wallet' as keyof TenantModules },
  ];

  const communityNavItems = [
    { label: t('nav.members'), href: '/members', icon: Users, feature: 'connections' as const },
    { label: t('nav.connections'), href: '/connections', icon: Users2, feature: 'connections' as keyof TenantFeatures },
    { label: t('nav.events'), href: '/events', icon: Calendar, feature: 'events' as const },
    { label: t('nav.groups'), href: '/groups', icon: Users, feature: 'groups' as const },
    { label: t('nav.volunteering'), href: '/volunteering', icon: Heart, feature: 'volunteering' as const },
    { label: t('nav.resources'), href: '/resources', icon: FolderOpen, feature: 'resources' as const },
    { label: t('nav.jobs'), href: '/jobs', icon: Briefcase, feature: 'job_vacancies' as const },
    { label: t('nav.marketplace', 'Marketplace'), href: '/marketplace', icon: ShoppingBag, feature: 'marketplace' as const },
  ];

  const engageNavItems = [
    { label: t('nav.goals'), href: '/goals', icon: Target, feature: 'goals' as const },
    { label: t('nav.polls'), href: '/polls', icon: BarChart3, feature: 'polls' as const },
    { label: t('nav.ideation'), href: '/ideation', icon: Lightbulb, feature: 'ideation_challenges' as keyof TenantFeatures },
  ];

  const exploreNavItems = [
    { label: t('nav.matches', 'Matches'), href: '/matches', icon: Handshake },
    { label: t('nav.achievements'), href: '/achievements', icon: Trophy, feature: 'gamification' as const },
    { label: t('nav.leaderboard'), href: '/leaderboard', icon: Medal, feature: 'gamification' as const },
    { label: t('nav.nexus_score', 'NexusScore'), href: '/nexus-score', icon: BarChart3, feature: 'gamification' as const },
    { label: t('nav.skills', 'Skills'), href: '/skills', icon: GraduationCap },
    { label: t('nav.saved', 'Saved'), href: '/saved', icon: Bookmark, auth: true },
    { label: t('nav.activity', 'My Activity'), href: '/activity', icon: Activity },
    { label: t('nav.ai_chat', 'AI Assistant'), href: '/chat', icon: Bot, feature: 'ai_chat' as keyof TenantFeatures },
  ];

  const federationNavItems = [
    { label: t('nav.federation_hub'), href: '/federation', icon: Globe, feature: 'federation' as keyof TenantFeatures },
    { label: t('nav.partner_communities'), href: '/federation/partners', icon: Building2, feature: 'federation' as keyof TenantFeatures },
    { label: t('nav.federated_members'), href: '/federation/members', icon: Users, feature: 'federation' as keyof TenantFeatures },
    { label: t('nav.federated_messages'), href: '/federation/messages', icon: MessageSquare, feature: 'federation' as keyof TenantFeatures },
    { label: t('nav.federated_listings'), href: '/federation/listings', icon: ListTodo, feature: 'federation' as keyof TenantFeatures },
    { label: t('nav.federated_events'), href: '/federation/events', icon: Calendar, feature: 'federation' as keyof TenantFeatures },
    { label: t('nav.federation_settings'), href: '/federation/settings', icon: Settings, feature: 'federation' as keyof TenantFeatures },
  ];

  const aboutNavItems = [
    { label: t('nav.about'), href: '/about', icon: Info },
    { label: t('nav.blog'), href: '/blog', icon: BookOpen, feature: 'blog' as const },
    { label: t('nav.faq'), href: '/faq', icon: HelpCircle },
    { label: t('nav.timebanking_guide'), href: '/timebanking-guide', icon: BookOpen },
  ];

  const hourTimebankAboutItems = [
    { label: t('nav.partner_with_us'), href: '/partner', icon: Handshake },
    { label: t('nav.social_prescribing'), href: '/social-prescribing', icon: Stethoscope },
    { label: t('nav.our_impact'), href: '/impact-summary', icon: TrendingUp },
    { label: t('nav.impact_report'), href: '/impact-report', icon: BarChart3 },
    { label: t('nav.strategic_plan'), href: '/strategic-plan', icon: Compass },
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
    if (item.feature && !hasFeature(item.feature)) return null;
    if (item.module && !hasModule(item.module)) return null;
    if (item.auth && !isAuthenticated) return null;

    const Icon = item.icon;
    const resolvedHref = tenantPath(item.href);
    const isActive = location.pathname === resolvedHref || location.pathname.startsWith(resolvedHref + '/');

    return (
      <Button
        key={item.href}
        variant="light"
        onPress={() => { onClose(); setTimeout(() => navigate(resolvedHref), 150); }}
        className={`flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-all w-full text-start h-auto justify-start ${
          isActive
            ? 'bg-theme-active text-theme-primary'
            : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover'
        }`}
      >
        <Icon className="w-4 h-4" aria-hidden="true" />
        <span>{item.label}</span>
      </Button>
    );
  };

  // Filter nav arrays to count visible items (for hiding empty sections)
  const visibleTimebanking = timebankingNavItems.filter(i => {
    if ('feature' in i && i.feature) return hasFeature(i.feature as keyof TenantFeatures);
    if ('module' in i && i.module) return hasModule(i.module as keyof TenantModules);
    return true;
  });
  const visibleCommunity = communityNavItems.filter(i => !i.feature || hasFeature(i.feature));
  const visibleEngage = engageNavItems.filter(i => !i.feature || hasFeature(i.feature as keyof TenantFeatures));
  const visibleExplore = exploreNavItems.filter(i => !i.feature || hasFeature(i.feature));
  const visibleFederation = federationNavItems.filter(i => !i.feature || hasFeature(i.feature));

  // Accordion section header style
  const sectionTitleClass = 'text-xs font-semibold uppercase tracking-wider text-theme-subtle';

  return (
    <Drawer
      isOpen={isOpen}
      onClose={onClose}
      placement="right"
      size="sm"
      hideCloseButton
      classNames={{
        base: 'bg-[var(--surface-dropdown)] border-l border-[var(--border-default)] shadow-2xl',
        header: 'border-b border-[var(--border-default)] p-4',
        body: 'p-0',
      }}
    >
      <DrawerContent style={{ paddingTop: 'env(safe-area-inset-top, 0px)', paddingBottom: 'env(safe-area-inset-bottom, 0px)' }}>
        {/* Header */}
        <DrawerHeader className="flex items-center justify-between">
          <TenantLogo size="lg" showName />
          <Button
            isIconOnly
            variant="light"
            className="text-theme-muted hover:text-theme-primary"
            onPress={onClose}
            aria-label={t('accessibility.close_menu')}
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
                aria-label={t('aria.open_search')}
              >
                <Search className="w-4 h-4" aria-hidden="true" />
                <span>{t('search.placeholder')}</span>
              </Button>
            </div>
          )}

          {/* User Section */}
          {isAuthenticated && user && (
            <div className="p-4 border-b border-[var(--border-default)]">
              <Button
                variant="light"
                onPress={() => { onClose(); setTimeout(() => navigate(tenantPath('/profile')), 150); }}
                className="flex items-center gap-3 w-full text-start h-auto p-0 justify-start"
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
              </Button>

              {/* Identity Verification Status */}
              <VerificationBadgeRow userId={user.id} size="sm" />
              <IdentityVerificationCTA userId={user.id} tenantPath={tenantPath} onClose={onClose} />

              {/* Quick Stats */}
              <div className="grid grid-cols-3 gap-2 mt-3">
                <Button
                  variant="flat"
                  onPress={() => { onClose(); setTimeout(() => navigate(tenantPath('/wallet')), 150); }}
                  className="text-center p-2 rounded-xl bg-theme-elevated hover:bg-theme-hover transition-colors h-auto flex-col"
                >
                  <p className="text-lg font-bold text-theme-primary">
                    {user.balance ?? 0}
                  </p>
                  <p className="text-xs text-theme-subtle">{t('stats.credits')}</p>
                </Button>
                <Button
                  variant="flat"
                  onPress={() => { onClose(); setTimeout(() => navigate(tenantPath('/messages')), 150); }}
                  className="text-center p-2 rounded-xl bg-theme-elevated hover:bg-theme-hover transition-colors relative h-auto flex-col"
                >
                  <p className="text-lg font-bold text-theme-primary">
                    {counts.messages > 0 ? counts.messages : 0}
                  </p>
                  <p className="text-xs text-theme-subtle">{t('stats.messages')}</p>
                  {counts.messages > 0 && (
                    <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full" aria-hidden="true" />
                  )}
                </Button>
                <Button
                  variant="flat"
                  onPress={() => { onClose(); setTimeout(() => navigate(tenantPath('/notifications')), 150); }}
                  className="text-center p-2 rounded-xl bg-theme-elevated hover:bg-theme-hover transition-colors relative h-auto flex-col"
                >
                  <p className="text-lg font-bold text-theme-primary">
                    {unreadCount > 0 ? unreadCount : 0}
                  </p>
                  <p className="text-xs text-theme-subtle">{t('stats.alerts')}</p>
                  {unreadCount > 0 && (
                    <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full" aria-hidden="true" />
                  )}
                </Button>
              </div>
            </div>
          )}

          {/* Navigation — Collapsible Sections */}
          <nav className="flex-1 overflow-y-auto" aria-label={t('aria.mobile_navigation')}>
            {hasCustomMenus ? (
              <div className="p-4 space-y-1">
                <MobileMenuItems menus={apiMenus} />
              </div>
            ) : (
            <Accordion
              selectionMode="multiple"
              selectedKeys={expandedKeys}
              onSelectionChange={(keys) => setExpandedKeys(keys as Set<string>)}
              className="px-2 py-2"
              itemClasses={{
                base: 'py-0',
                title: sectionTitleClass,
                trigger: 'py-2 px-2',
                content: 'pb-2 pt-0',
                indicator: 'text-theme-subtle',
              }}
            >
              {/* Main Navigation */}
              <AccordionItem key="main" title={t('sections.main', 'Main')} aria-label={t('aria.main_navigation')}>
                <div className="space-y-0.5">
                  {mainNavItems.map(renderNavLink)}
                </div>
              </AccordionItem>

              {/* Timebanking */}
              {visibleTimebanking.length > 0 ? (
                <AccordionItem key="timebanking" title={t('nav.timebanking', 'Timebanking')} aria-label={t('aria.timebanking_navigation')}>
                  <div className="space-y-0.5">
                    {timebankingNavItems.map(renderNavLink)}
                  </div>
                </AccordionItem>
              ) : null}

              {/* Community */}
              {visibleCommunity.length > 0 ? (
                <AccordionItem key="community" title={t('sections.community')} aria-label={t('aria.community_navigation')}>
                  <div className="space-y-0.5">
                    {communityNavItems.map(renderNavLink)}
                  </div>
                </AccordionItem>
              ) : null}

              {/* Engage */}
              {visibleEngage.length > 0 ? (
                <AccordionItem key="engage" title={t('sections.engage', 'Engage')} aria-label={t('aria.engage_navigation')}>
                  <div className="space-y-0.5">
                    {engageNavItems.map(renderNavLink)}
                  </div>
                </AccordionItem>
              ) : null}

              {/* Explore / Activity */}
              {visibleExplore.length > 0 ? (
                <AccordionItem key="explore" title={t('sections.explore')} aria-label={t('aria.explore_navigation')}>
                  <div className="space-y-0.5">
                    {exploreNavItems.map(renderNavLink)}
                  </div>
                </AccordionItem>
              ) : null}

              {/* Federation */}
              {visibleFederation.length > 0 && isAuthenticated ? (
                <AccordionItem
                  key="federation"
                  title={
                    <span className="flex items-center gap-1.5">
                      <Globe className="w-3 h-3" aria-hidden="true" />
                      {t('sections.federation')}
                    </span>
                  }
                  aria-label={t('aria.federation_navigation')}
                >
                  <div className="space-y-0.5">
                    {federationNavItems.map(renderNavLink)}
                  </div>
                </AccordionItem>
              ) : null}

              {/* About */}
              <AccordionItem key="about" title={t('sections.about')} aria-label={t('aria.about_navigation')}>
                <div className="space-y-0.5">
                  {aboutNavItems.map(renderNavLink)}
                  {tenant?.slug === 'hour-timebank' && hourTimebankAboutItems.map(renderNavLink)}
                  {(tenant?.menu_pages?.about || []).map((p: { title: string; slug: string }) => renderNavLink({
                    label: p.title,
                    href: `/page/${p.slug}`,
                    icon: FileText,
                  }))}
                </div>
              </AccordionItem>

              {/* Legal */}
              <AccordionItem key="legal" title={t('sections.legal')} aria-label={t('aria.legal_navigation')}>
                <div className="space-y-0.5">
                  {legalNavItems.map(renderNavLink)}
                  <Button
                    variant="light"
                    onPress={() => { resetConsent(); onClose(); }}
                    className="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-theme-muted hover:text-theme-primary hover:bg-theme-hover transition-all w-full justify-start h-auto"
                  >
                    <Settings className="w-4 h-4" aria-hidden="true" />
                    <span>{t('cookie_consent.manage', 'Cookie Settings')}</span>
                  </Button>
                </div>
              </AccordionItem>
            </Accordion>
            )}

            {/* Utility Row — consolidated secondary actions */}
            <div className="px-4 py-3 border-t border-[var(--border-default)]">
              <div className="flex items-center justify-between gap-2">
                {/* Left: Admin + Help links */}
                <div className="flex items-center gap-1 flex-wrap">
                  {isAuthenticated && (
                    <Button
                      variant="light"
                      size="sm"
                      className="text-theme-muted hover:text-theme-primary h-8 min-w-0 px-2 gap-1.5 text-xs"
                      onPress={() => { onClose(); setTimeout(() => navigate(tenantPath('/help')), 150); }}
                    >
                      <HelpCircle className="w-3.5 h-3.5" aria-hidden="true" />
                      {t('user_menu.help_center')}
                    </Button>
                  )}
                  <Button
                    variant="light"
                    size="sm"
                    className="text-amber-600 dark:text-amber-400 hover:text-amber-700 dark:hover:text-amber-300 h-8 min-w-0 px-2 gap-1.5 text-xs"
                    onPress={() => { onClose(); setTimeout(() => navigate(tenantPath(RELEASE_STATUS.readMorePath)), 150); }}
                  >
                    <FlaskConical className="w-3.5 h-3.5" aria-hidden="true" />
                    {t('dev_banner.dev_notice', 'Dev Notice')}
                  </Button>
                  {!isAuthenticated && (
                    <Button
                      variant="light"
                      size="sm"
                      className="text-theme-muted hover:text-theme-primary h-8 min-w-0 px-2 gap-1.5 text-xs"
                      onPress={() => { onClose(); setTimeout(() => navigate(tenantPath('/contact')), 150); }}
                    >
                      <MessageSquare className="w-3.5 h-3.5" aria-hidden="true" />
                      {t('support.contact')}
                    </Button>
                  )}
                  {isAuthenticated && isAdmin && (
                    <>
                      <Button
                        variant="light"
                        size="sm"
                        className="text-theme-muted hover:text-theme-primary h-8 min-w-0 px-2 gap-1.5 text-xs"
                        onPress={() => { onClose(); setTimeout(() => navigate(tenantPath('/admin')), 150); }}
                      >
                        <Shield className="w-3.5 h-3.5" aria-hidden="true" />
                        {t('user_menu.admin_panel')}
                      </Button>
                    </>
                  )}
                </div>

                {/* Right: Language + Theme */}
                <div className="flex items-center gap-1 shrink-0">
                  <LanguageSwitcher />
                  <Button
                    isIconOnly
                    variant="light"
                    size="sm"
                    className="text-theme-muted hover:text-theme-primary min-w-[44px] min-h-[44px]"
                    onPress={toggleTheme}
                    aria-label={`Switch to ${resolvedTheme === 'dark' ? 'light' : 'dark'} mode`}
                  >
                    {resolvedTheme === 'dark' ? (
                      <Sun className="w-4 h-4 text-amber-400" aria-hidden="true" />
                    ) : (
                      <Moon className="w-4 h-4 text-indigo-500" aria-hidden="true" />
                    )}
                  </Button>
                </div>
              </div>
            </div>

            {/* Account Actions */}
            {isAuthenticated && (
              <div className="px-4 py-3 border-t border-[var(--border-default)]">
                <div className="flex items-center gap-2">
                  <Button
                    variant="light"
                    onPress={() => { onClose(); setTimeout(() => navigate(tenantPath('/settings')), 150); }}
                    className="flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-xl text-sm font-medium text-theme-muted hover:text-theme-primary hover:bg-theme-hover border border-[var(--border-default)] transition-all h-auto"
                  >
                    <Settings className="w-4 h-4" aria-hidden="true" />
                    <span>{t('account.settings')}</span>
                  </Button>
                  <Button
                    variant="light"
                    onPress={handleLogout}
                    className="flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-xl text-sm font-medium text-red-500 dark:text-red-400 hover:bg-red-500/10 transition-all h-auto border border-red-500/20"
                  >
                    <LogOut className="w-4 h-4" aria-hidden="true" />
                    <span>{t('account.log_out')}</span>
                  </Button>
                </div>
              </div>
            )}

            {/* Auth buttons for guests */}
            {!isAuthenticated && (
              <div className="px-4 py-3 border-t border-[var(--border-default)] space-y-2">
                <Button
                  variant="flat"
                  className="w-full bg-theme-elevated text-theme-secondary"
                  onPress={() => { onClose(); navigate(tenantPath('/login')); }}
                >
                  {t('auth.log_in')}
                </Button>
                <Button
                  className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium"
                  onPress={() => { onClose(); navigate(tenantPath('/register')); }}
                >
                  {t('auth.sign_up')}
                </Button>
              </div>
            )}

            {/* Attribution (AGPL Section 7(b) — required on all pages) */}
            <div className="pt-4 pb-4 px-4">
              <Divider className="bg-theme-elevated mb-3" />
              <a
                href="https://github.com/jasperfordesq-ai/nexus-v1"
                target="_blank"
                rel="noopener noreferrer"
                className="block text-center text-xs text-theme-subtle hover:text-theme-primary transition-colors"
              >
                Built on Project NEXUS by Jasper Ford
              </a>
              <div className="flex justify-center gap-2 mt-2">
                <Button
                  variant="light"
                  size="sm"
                  onPress={() => { onClose(); setTimeout(() => navigate(tenantPath('/platform/terms')), 150); }}
                  className="text-[10px] text-theme-subtle hover:text-theme-primary transition-colors h-auto p-0 min-w-0"
                >
                  Platform Terms
                </Button>
                <span className="text-theme-subtle/30">&middot;</span>
                <Button
                  variant="light"
                  size="sm"
                  onPress={() => { onClose(); setTimeout(() => navigate(tenantPath('/platform/privacy')), 150); }}
                  className="text-[10px] text-theme-subtle hover:text-theme-primary transition-colors h-auto p-0 min-w-0"
                >
                  Privacy
                </Button>
              </div>
            </div>
          </nav>
        </DrawerBody>
      </DrawerContent>
    </Drawer>
  );
}

export default MobileDrawer;
