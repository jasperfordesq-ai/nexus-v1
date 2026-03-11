// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Main Navigation Bar
 * Responsive header with utility bar, desktop nav, and mobile menu trigger.
 * Desktop uses grouped dropdowns and a mega menu for cleaner layout.
 * Theme-aware styling for light and dark modes.
 */

import { useState, useEffect, useMemo, useCallback } from 'react';
import { DevelopmentStatusBanner } from './DevelopmentStatusBanner';
import { Link, NavLink, useNavigate, useLocation } from 'react-router-dom';
import {
  Button,
  Avatar,
  Badge,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  DropdownSection,
} from '@heroui/react';
import {
  Hexagon,
  LayoutDashboard,
  ListTodo,
  MessageSquare,
  Wallet,
  Users,
  Users2,
  Calendar,
  Bell,
  Settings,
  LogOut,
  Menu,
  Search,
  Plus,
  Sun,
  Moon,
  ArrowRightLeft,
  ChevronDown,
  Trophy,
  Medal,
  Target,
  HelpCircle,
  UserCircle,
  Newspaper,
  BookOpen,
  FolderOpen,
  Heart,
  Building2,
  Globe,
  Info,
  FileText,
  Shield,
  Handshake,
  Stethoscope,
  TrendingUp,
  BarChart3,
  Compass,
  Bot,
  Briefcase,
  Lightbulb,
  GraduationCap,
  Activity,
  Library,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useAuth, useTenant, useNotifications, useTheme, useMenuContext } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';
import { navigateToLegacyAdmin } from '@/lib/nav-helpers';
import { LanguageSwitcher } from '@/components/LanguageSwitcher';
import { DesktopMenuItems } from '@/components/navigation';
import { SearchOverlay } from '@/components/layout/SearchOverlay';
import { MegaMenu } from '@/components/layout/MegaMenu';

interface NavbarProps {
  onMobileMenuOpen?: () => void;
  /** External control for search overlay (from MobileDrawer) */
  externalSearchOpen?: boolean;
  onSearchOpenChange?: (open: boolean) => void;
}

export function Navbar({ onMobileMenuOpen, externalSearchOpen, onSearchOpenChange }: NavbarProps) {
  const navigate = useNavigate();
  const location = useLocation();
  const { t } = useTranslation('common');
  const { user, isAuthenticated, logout } = useAuth();
  const { tenant, branding, hasFeature, hasModule, tenantPath } = useTenant();
  const { unreadCount, counts } = useNotifications();
  const { resolvedTheme, toggleTheme } = useTheme();
  const { headerMenus, hasCustomMenus } = useMenuContext();

  // Compute admin status once
  const isAdmin = Boolean(user?.role === 'admin' || user?.role === 'tenant_admin' || user?.role === 'super_admin' || user?.is_admin || user?.is_super_admin || user?.is_tenant_super_admin);

  // Search state — can be controlled externally
  const [internalSearchOpen, setInternalSearchOpen] = useState(false);
  const isSearchOpen = externalSearchOpen ?? internalSearchOpen;
  const setIsSearchOpen = useCallback((open: boolean) => {
    setInternalSearchOpen(open);
    onSearchOpenChange?.(open);
  }, [onSearchOpenChange]);

  // Dropdown state - controlled to fix close behavior
  const [communityOpen, setCommunityOpen] = useState(false);
  const [moreOpen, setMoreOpen] = useState(false);
  const [createOpen, setCreateOpen] = useState(false);
  const [userOpen, setUserOpen] = useState(false);

  const closeAllDropdowns = useCallback(() => {
    setCommunityOpen(false);
    setMoreOpen(false);
    setCreateOpen(false);
    setUserOpen(false);
  }, []);

  const handleCommunityOpenChange = useCallback((open: boolean) => {
    if (open) { setMoreOpen(false); setCreateOpen(false); setUserOpen(false); }
    setCommunityOpen(open);
  }, []);
  const handleMoreOpenChange = useCallback((open: boolean) => {
    if (open) { setCommunityOpen(false); setCreateOpen(false); setUserOpen(false); }
    setMoreOpen(open);
  }, []);
  const handleCreateOpenChange = useCallback((open: boolean) => {
    if (open) { setCommunityOpen(false); setMoreOpen(false); setUserOpen(false); }
    setCreateOpen(open);
  }, []);
  const handleUserOpenChange = useCallback((open: boolean) => {
    if (open) { setCommunityOpen(false); setMoreOpen(false); setCreateOpen(false); }
    setUserOpen(open);
  }, []);

  const dropdownNavigate = useCallback((path: string) => {
    closeAllDropdowns();
    requestAnimationFrame(() => {
      navigate(path);
    });
  }, [closeAllDropdowns, navigate]);

  const handleLogout = async () => {
    closeAllDropdowns();
    await logout();
    navigate(tenantPath('/login'));
  };

  // Close all dropdowns when route changes
  useEffect(() => {
    closeAllDropdowns();
  }, [location.pathname, closeAllDropdowns]);

  // Keyboard shortcut: Ctrl/Cmd+K opens search
  useEffect(() => {
    function handleKeyDown(e: KeyboardEvent) {
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        setIsSearchOpen(true);
      }
    }
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [setIsSearchOpen]);

  // Check if current path matches any in a group
  const isActiveGroup = (paths: string[]) => {
    return paths.some(path => location.pathname.startsWith(path));
  };

  // ─── Memoized nav item arrays ──────────────────────────────────────────────
  const isHourTimebank = tenant?.slug === 'hour-timebank';

  const communityItems = useMemo(() => [
    { label: t('nav.members'), desc: t('nav_desc.members'), href: tenantPath('/members'), icon: Users, feature: 'connections' as const },
    { label: t('nav.connections'), desc: t('nav_desc.connections'), href: tenantPath('/connections'), icon: Users2, feature: 'connections' as const },
    { label: t('nav.events'), desc: t('nav_desc.events'), href: tenantPath('/events'), icon: Calendar, feature: 'events' as const },
    { label: t('nav.groups'), desc: t('nav_desc.groups'), href: tenantPath('/groups'), icon: Users, feature: 'groups' as const },
    { label: t('nav.blog'), desc: t('nav_desc.blog'), href: tenantPath('/blog'), icon: BookOpen, feature: 'blog' as const },
    { label: t('nav.volunteering'), desc: t('nav_desc.volunteering'), href: tenantPath('/volunteering'), icon: Heart, feature: 'volunteering' as const },
    { label: t('nav.organisations'), desc: t('nav_desc.organisations'), href: tenantPath('/organisations'), icon: Building2, feature: 'organisations' as const },
    { label: t('nav.resources'), desc: t('nav_desc.resources'), href: tenantPath('/resources'), icon: FolderOpen, feature: 'resources' as const },
    { label: t('nav.knowledge_base', 'Knowledge Base'), desc: t('nav_desc.knowledge_base'), href: tenantPath('/kb'), icon: Library, feature: 'resources' as const },
    { label: t('nav.polls'), desc: t('nav_desc.polls'), href: tenantPath('/polls'), icon: BarChart3, feature: 'polls' as const },
    { label: t('nav.jobs'), desc: t('nav_desc.jobs'), href: tenantPath('/jobs'), icon: Briefcase, feature: 'job_vacancies' as const },
    { label: t('nav.ideation'), desc: t('nav_desc.ideation'), href: tenantPath('/ideation'), icon: Lightbulb, feature: 'ideation_challenges' as const },
  ].filter(item => hasFeature(item.feature)), [t, tenantPath, hasFeature]);

  const activityItems = useMemo(() => [
    { label: t('nav.exchanges'), desc: t('nav_desc.exchanges'), href: tenantPath('/exchanges'), icon: ArrowRightLeft, feature: 'exchange_workflow' as const },
    { label: t('nav.group_exchanges'), desc: t('nav_desc.group_exchanges'), href: tenantPath('/group-exchanges'), icon: Users, feature: 'group_exchanges' as const },
    { label: t('nav.wallet'), desc: t('nav_desc.wallet'), href: tenantPath('/wallet'), icon: Wallet, module: 'wallet' as const },
    { label: t('nav.achievements'), desc: t('nav_desc.achievements'), href: tenantPath('/achievements'), icon: Trophy, feature: 'gamification' as const, dividerBefore: true },
    { label: t('nav.leaderboard'), desc: t('nav_desc.leaderboard'), href: tenantPath('/leaderboard'), icon: Medal, feature: 'gamification' as const },
    { label: t('nav.nexus_score', 'NexusScore'), desc: t('nav_desc.nexus_score'), href: tenantPath('/nexus-score'), icon: BarChart3, feature: 'gamification' as const },
    { label: t('nav.matches', 'Matches'), desc: t('nav_desc.matches'), href: tenantPath('/matches'), icon: Handshake, dividerBefore: true },
    { label: t('nav.goals'), desc: t('nav_desc.goals'), href: tenantPath('/goals'), icon: Target, feature: 'goals' as const },
    { label: t('nav.skills', 'Skills'), desc: t('nav_desc.skills'), href: tenantPath('/skills'), icon: GraduationCap },
    { label: t('nav.activity', 'My Activity'), desc: t('nav_desc.activity'), href: tenantPath('/activity'), icon: Activity },
    { label: t('nav.ai_chat', 'AI Assistant'), desc: t('nav_desc.ai_chat'), href: tenantPath('/chat'), icon: Bot, feature: 'ai_chat' as const },
  ].filter(item => {
    if ('feature' in item && item.feature && !hasFeature(item.feature)) return false;
    if ('module' in item && item.module && !hasModule(item.module)) return false;
    return true;
  }), [t, tenantPath, hasFeature, hasModule]);

  const federationItems = useMemo(() => hasFeature('federation') ? [
    { label: t('nav.federation_hub'), desc: t('nav_desc.federation_hub'), href: tenantPath('/federation'), icon: Globe },
    { label: t('nav.partner_communities'), desc: t('nav_desc.partner_communities'), href: tenantPath('/federation/partners'), icon: Building2 },
    { label: t('nav.federated_members'), desc: t('nav_desc.federated_members'), href: tenantPath('/federation/members'), icon: Users },
    { label: t('nav.federated_messages'), desc: t('nav_desc.federated_messages'), href: tenantPath('/federation/messages'), icon: MessageSquare },
    { label: t('nav.federated_listings'), desc: t('nav_desc.federated_listings'), href: tenantPath('/federation/listings'), icon: ListTodo },
    { label: t('nav.federated_events'), desc: t('nav_desc.federated_events'), href: tenantPath('/federation/events'), icon: Calendar },
    { label: t('nav.federation_settings'), desc: t('nav_desc.federation_settings'), href: tenantPath('/federation/settings'), icon: Settings },
  ] : [], [t, tenantPath, hasFeature]);

  const aboutItems = useMemo(() => [
    { label: t('nav.about'), desc: t('nav_desc.about'), href: tenantPath('/about'), icon: Info },
    { label: t('nav.faq'), desc: t('nav_desc.faq'), href: tenantPath('/faq'), icon: HelpCircle },
    { label: t('nav.timebanking_guide'), desc: t('nav_desc.timebanking_guide'), href: tenantPath('/timebanking-guide'), icon: BookOpen },
    ...(isHourTimebank ? [
      { label: t('nav.partner_with_us'), desc: t('nav_desc.partner_with_us'), href: tenantPath('/partner'), icon: Handshake },
      { label: t('nav.social_prescribing'), desc: t('nav_desc.social_prescribing'), href: tenantPath('/social-prescribing'), icon: Stethoscope },
      { label: t('nav.our_impact'), desc: t('nav_desc.our_impact'), href: tenantPath('/impact-summary'), icon: TrendingUp },
      { label: t('nav.impact_report'), desc: t('nav_desc.impact_report'), href: tenantPath('/impact-report'), icon: BarChart3 },
      { label: t('nav.strategic_plan'), desc: t('nav_desc.strategic_plan'), href: tenantPath('/strategic-plan'), icon: Compass },
    ] : []),
    ...(tenant?.menu_pages?.about || []).map((p: { title: string; slug: string }) => ({
      label: p.title,
      desc: undefined as string | undefined,
      href: tenantPath(`/page/${p.slug}`),
      icon: FileText,
    })),
  ], [t, tenantPath, isHourTimebank, tenant?.menu_pages?.about]);

  const communityPaths = useMemo(() => communityItems.map(i => i.href), [communityItems]);
  const morePaths = useMemo(() => [
    ...activityItems.map(i => i.href),
    ...federationItems.map(i => i.href),
    ...aboutItems.map(i => i.href),
  ], [activityItems, federationItems, aboutItems]);

  return (
    <>
      {/* Skip to content link — accessible to keyboard/screen reader users */}
      <a
        href="#main-content"
        className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[500] focus:px-4 focus:py-2 focus:bg-indigo-600 focus:text-white focus:rounded-lg focus:text-sm focus:font-medium"
      >
        {t('accessibility.skip_to_content', 'Skip to main content')}
      </a>

      <header className="fixed top-0 left-0 right-0 z-300 backdrop-blur-xl border-b border-theme-default glass-surface overflow-x-clip">
        {/* Development status banner — inside fixed header so it's always visible */}
        <DevelopmentStatusBanner />

        {/* Utility Bar — slim top strip for secondary actions */}
        <div className="hidden sm:block border-b border-[var(--border-default)] bg-[var(--surface-elevated)]">
          <div className="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="flex items-center justify-end gap-1 h-8 flex-nowrap overflow-x-auto">
              {/* Help Center — authenticated users */}
              {isAuthenticated && (
                <Button
                  variant="light"
                  size="sm"
                  className="text-theme-muted hover:text-theme-primary h-7 min-w-0 px-2 gap-1 text-xs shrink-0"
                  onPress={() => navigate(tenantPath('/help'))}
                >
                  <HelpCircle className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                  <span className="hidden md:inline">{t('user_menu.help_center')}</span>
                </Button>
              )}
              {/* Admin links — admin users only */}
              {isAuthenticated && isAdmin && (
                <>
                  <span className="text-[var(--border-default)] text-xs select-none shrink-0">|</span>
                  <Button
                    variant="light"
                    size="sm"
                    className="text-theme-muted hover:text-theme-primary h-7 min-w-0 px-2 gap-1 text-xs shrink-0"
                    onPress={() => navigate('/admin')}
                    aria-label={t('user_menu.admin_panel')}
                  >
                    <Shield className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                    <span className="hidden md:inline">{t('user_menu.admin_panel')}</span>
                  </Button>
                  <Button
                    variant="light"
                    size="sm"
                    className="text-theme-muted hover:text-theme-primary h-7 min-w-0 px-2 gap-1 text-xs shrink-0"
                    onPress={navigateToLegacyAdmin}
                    aria-label={t('user_menu.legacy_admin')}
                  >
                    <LayoutDashboard className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                    <span className="hidden md:inline">{t('user_menu.legacy_admin')}</span>
                  </Button>
                </>
              )}
              {isAuthenticated && <span className="text-[var(--border-default)] text-xs select-none shrink-0">|</span>}
              <LanguageSwitcher />
              <Button
                isIconOnly
                variant="light"
                size="sm"
                className="text-theme-muted hover:text-theme-primary w-7 h-7 min-w-7 shrink-0"
                onPress={toggleTheme}
                aria-label={`Switch to ${resolvedTheme === 'dark' ? 'light' : 'dark'} mode`}
              >
                {resolvedTheme === 'dark' ? (
                  <Sun className="w-3.5 h-3.5 text-amber-400" aria-hidden="true" />
                ) : (
                  <Moon className="w-3.5 h-3.5 text-indigo-500" aria-hidden="true" />
                )}
              </Button>
            </div>
          </div>
        </div>

        {/* Main Navigation Bar */}
        <div className="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between h-14 sm:h-16">
            {/* Left Section: Mobile Menu + Brand */}
            <div className="flex items-center gap-2 sm:gap-3">
              {/* Mobile Menu Toggle */}
              <Button
                isIconOnly
                variant="light"
                size="sm"
                className="lg:hidden text-theme-muted hover:text-theme-primary"
                onPress={onMobileMenuOpen}
                aria-label="Open menu"
              >
                <Menu className="w-5 h-5" aria-hidden="true" />
              </Button>

              {/* Brand */}
              <Link to={tenantPath('/')} className="flex items-center gap-2">
                {branding.logo ? (
                  <img
                    src={branding.logo}
                    alt={branding.name}
                    className="h-8 sm:h-9 w-auto object-contain"
                    loading="eager"
                  />
                ) : (
                  <Hexagon className="w-7 h-7 sm:w-8 sm:h-8 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
                )}
                <span className="font-bold text-lg sm:text-xl text-gradient hidden min-[480px]:inline">
                  {branding.name}
                </span>
              </Link>
            </div>

            {/* Desktop Navigation */}
            <nav className="hidden lg:flex items-center gap-1" aria-label="Main navigation">
              {hasCustomMenus ? (
                <DesktopMenuItems menus={headerMenus} />
              ) : (
              <>
              {/* Dashboard */}
              {hasModule('dashboard') && (
                <NavLink
                  to={tenantPath('/dashboard')}
                  className={({ isActive }) =>
                    `flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all ${
                      isActive
                        ? 'bg-theme-active text-theme-primary'
                        : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover'
                    }`
                  }
                >
                  <LayoutDashboard className="w-4 h-4" aria-hidden="true" />
                  <span>{t('nav.dashboard')}</span>
                </NavLink>
              )}

              {/* Feed */}
              {hasModule('feed') && (
                <NavLink
                  to={tenantPath('/feed')}
                  className={({ isActive }) =>
                    `flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all ${
                      isActive
                        ? 'bg-theme-active text-theme-primary'
                        : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover'
                    }`
                  }
                >
                  <Newspaper className="w-4 h-4" aria-hidden="true" />
                  <span>{t('nav.feed')}</span>
                </NavLink>
              )}

              {/* Listings */}
              {hasModule('listings') && (
                <NavLink
                  to={tenantPath('/listings')}
                  className={({ isActive }) =>
                    `flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all ${
                      isActive
                        ? 'bg-theme-active text-theme-primary'
                        : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover'
                    }`
                  }
                >
                  <ListTodo className="w-4 h-4" aria-hidden="true" />
                  <span>{t('nav.listings')}</span>
                </NavLink>
              )}

              {/* Messages */}
              {hasModule('messages') && (
                <NavLink
                  to={tenantPath('/messages')}
                  className={({ isActive }) =>
                    `flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all ${
                      isActive
                        ? 'bg-theme-active text-theme-primary'
                        : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover'
                    }`
                  }
                >
                  <MessageSquare className="w-4 h-4" aria-hidden="true" />
                  <span>{t('nav.messages')}</span>
                  {counts.messages > 0 && isAuthenticated && (
                    <span className="ml-1 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold bg-red-500 text-white rounded-full">
                      {counts.messages > 99 ? '99+' : counts.messages}
                    </span>
                  )}
                </NavLink>
              )}

              {/* Community Dropdown */}
              {communityItems.length > 0 && (
                <Dropdown placement="bottom-start" isOpen={communityOpen} onOpenChange={handleCommunityOpenChange} shouldBlockScroll={false}>
                  <DropdownTrigger>
                    <Button
                      variant="light"
                      size="sm"
                      className={`flex items-center gap-1 px-3 py-2 text-sm font-medium transition-all ${
                        isActiveGroup(communityPaths)
                          ? 'bg-theme-active text-theme-primary'
                          : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover'
                      }`}
                      endContent={<ChevronDown className="w-3 h-3" aria-hidden="true" />}
                    >
                      <Users className="w-4 h-4" aria-hidden="true" />
                      {t('nav.community')}
                    </Button>
                  </DropdownTrigger>
                  <DropdownMenu
                    aria-label="Community navigation"
                    className="min-w-[220px]"
                    classNames={{
                      base: 'bg-[var(--surface-dropdown)] border border-[var(--border-default)] shadow-xl max-h-[70vh] overflow-y-auto',
                    }}
                    onAction={(key) => {
                      dropdownNavigate(String(key));
                    }}
                  >
                    {communityItems.map((item) => (
                      <DropdownItem
                        key={item.href}
                        description={item.desc}
                        startContent={<item.icon className="w-4 h-4" aria-hidden="true" />}
                        className={location.pathname.startsWith(item.href) ? 'bg-theme-active' : ''}
                      >
                        {item.label}
                      </DropdownItem>
                    ))}
                  </DropdownMenu>
                </Dropdown>
              )}

              {/* More — Multi-column mega menu */}
              <MegaMenu
                isOpen={moreOpen}
                onOpenChange={handleMoreOpenChange}
                isActive={isActiveGroup(morePaths)}
                activityItems={activityItems}
                federationItems={federationItems}
                aboutItems={aboutItems}
                onNavigate={dropdownNavigate}
              />
              </>
              )}
            </nav>

            {/* User Actions */}
            <div className="flex items-center gap-1 sm:gap-2">
              {/* Search Trigger — visible to all users */}
              <Button
                variant="flat"
                size="sm"
                className="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-lg border border-theme-default bg-theme-elevated hover:bg-theme-hover text-theme-subtle text-sm h-auto"
                onPress={() => setIsSearchOpen(true)}
                aria-label="Search (Ctrl+K)"
              >
                <Search className="w-4 h-4" aria-hidden="true" />
                <span className="text-theme-subtle">{t('search.placeholder')}</span>
                <kbd className="ml-2 hidden lg:inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-theme-hover text-[10px] font-medium text-theme-subtle border border-theme-default">
                  <span className="text-xs">⌘</span>K
                </kbd>
              </Button>
              <Button
                isIconOnly
                variant="light"
                size="sm"
                className="flex md:hidden text-theme-muted hover:text-theme-primary"
                onPress={() => setIsSearchOpen(true)}
                aria-label="Search"
              >
                <Search className="w-5 h-5" aria-hidden="true" />
              </Button>

              {isAuthenticated ? (
                <>
                  {/* Create Button */}
                  <Dropdown placement="bottom-end" isOpen={createOpen} onOpenChange={handleCreateOpenChange} shouldBlockScroll={false}>
                    <DropdownTrigger>
                      <Button
                        isIconOnly
                        size="sm"
                        className="hidden sm:flex bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                        aria-label="Create new"
                      >
                        <Plus className="w-4 h-4" aria-hidden="true" />
                      </Button>
                    </DropdownTrigger>
                    <DropdownMenu
                      aria-label="Create actions"
                      classNames={{
                        base: 'bg-[var(--surface-dropdown)] border border-[var(--border-default)] shadow-xl max-h-[70vh] overflow-y-auto',
                      }}
                      onAction={(key) => {
                        dropdownNavigate(String(key));
                      }}
                    >
                      <DropdownItem
                        key={tenantPath('/listings/create')}
                        startContent={<ListTodo className="w-4 h-4" aria-hidden="true" />}
                      >
                        {t('create.new_listing')}
                      </DropdownItem>
                      {hasFeature('events') ? (
                        <DropdownItem
                          key={tenantPath('/events/create')}
                          startContent={<Calendar className="w-4 h-4" aria-hidden="true" />}
                        >
                          {t('create.new_event')}
                        </DropdownItem>
                      ) : null}
                    </DropdownMenu>
                  </Dropdown>

                  {/* Language Switcher — mobile only (desktop uses utility bar) */}
                  <div className="sm:hidden">
                    <LanguageSwitcher />
                  </div>

                  {/* Notifications */}
                  {/* Visually hidden live region announces unread count changes to screen readers */}
                  <span className="sr-only" aria-live="polite" aria-atomic="true">
                    {unreadCount > 0 ? t('nav.unread_notifications', '{{count}} unread notifications', { count: unreadCount }) : ''}
                  </span>
                  <Badge
                    content={unreadCount > 99 ? '99+' : unreadCount}
                    color="danger"
                    size="sm"
                    isInvisible={unreadCount === 0}
                    placement="top-right"
                  >
                    <Button
                      isIconOnly
                      variant="light"
                      size="sm"
                      className={`text-theme-muted hover:text-theme-primary ${unreadCount > 0 ? 'text-indigo-500 dark:text-indigo-400' : ''}`}
                      onPress={() => navigate(tenantPath('/notifications'))}
                      aria-label={`Notifications${unreadCount > 0 ? `, ${unreadCount} unread` : ''}`}
                    >
                      <Bell className="w-4 h-4 sm:w-5 sm:h-5" aria-hidden="true" />
                    </Button>
                  </Badge>

                  {/* User Dropdown */}
                  <Dropdown placement="bottom-end" isOpen={userOpen} onOpenChange={handleUserOpenChange} shouldBlockScroll={false}>
                    <DropdownTrigger>
                      <Avatar
                        as="button"
                        name={`${user?.first_name} ${user?.last_name}`}
                        src={resolveAvatarUrl(user?.avatar_url || user?.avatar)}
                        size="sm"
                        className="cursor-pointer ring-2 ring-transparent hover:ring-indigo-500/50 transition-all w-8 h-8 sm:w-9 sm:h-9"
                        showFallback
                      />
                    </DropdownTrigger>
                    <DropdownMenu
                      aria-label="User actions"
                      classNames={{
                        base: 'bg-[var(--surface-dropdown)] border border-[var(--border-default)] shadow-xl min-w-[220px] max-h-[70vh] overflow-y-auto',
                      }}
                      onAction={(key) => {
                        const k = String(key);
                        if (k === 'theme') { toggleTheme(); closeAllDropdowns(); return; }
                        if (k === 'logout') { handleLogout(); return; }
                        if (k === 'profile-header') return;
                        dropdownNavigate(k);
                      }}
                    >
                      <DropdownSection showDivider>
                        <DropdownItem
                          key="profile-header"
                          className="h-14 gap-2 cursor-default"
                          textValue="Profile"
                          isReadOnly
                        >
                          <p className="font-semibold text-theme-primary">
                            {user?.first_name} {user?.last_name}
                          </p>
                          <p className="text-sm text-theme-subtle">{user?.email}</p>
                        </DropdownItem>
                      </DropdownSection>

                      <DropdownSection showDivider>
                        <DropdownItem
                          key={tenantPath('/profile')}
                          startContent={<UserCircle className="w-4 h-4" aria-hidden="true" />}
                        >
                          {t('user_menu.my_profile')}
                        </DropdownItem>
                        {hasModule('wallet') ? (
                          <DropdownItem
                            key={tenantPath('/wallet')}
                            startContent={<Wallet className="w-4 h-4" aria-hidden="true" />}
                            endContent={
                              <span className="text-xs text-theme-subtle">
                                {user?.balance ?? 0}h
                              </span>
                            }
                          >
                            {t('user_menu.wallet')}
                          </DropdownItem>
                        ) : null}
                        <DropdownItem
                          key={tenantPath('/settings')}
                          startContent={<Settings className="w-4 h-4" aria-hidden="true" />}
                        >
                          {t('user_menu.settings')}
                        </DropdownItem>
                      </DropdownSection>

                      <DropdownSection showDivider>
                        <DropdownItem
                          key="theme"
                          startContent={
                            resolvedTheme === 'dark' ? (
                              <Sun className="w-4 h-4 text-amber-400" aria-hidden="true" />
                            ) : (
                              <Moon className="w-4 h-4 text-indigo-500" aria-hidden="true" />
                            )
                          }
                        >
                          {resolvedTheme === 'dark' ? t('user_menu.light_mode') : t('user_menu.dark_mode')}
                        </DropdownItem>
                      </DropdownSection>

                      <DropdownSection>
                        <DropdownItem
                          key="logout"
                          color="danger"
                          startContent={<LogOut className="w-4 h-4" aria-hidden="true" />}
                          className="text-red-500 dark:text-red-400"
                        >
                          {t('user_menu.log_out')}
                        </DropdownItem>
                      </DropdownSection>
                    </DropdownMenu>
                  </Dropdown>
                </>
              ) : (
                <>
                  {/* Theme Toggle + Language Switcher — mobile only (desktop uses utility bar) */}
                  <div className="flex items-center gap-1 sm:hidden">
                    <Button
                      isIconOnly
                      variant="light"
                      size="sm"
                      className="text-theme-muted hover:text-theme-primary"
                      onPress={toggleTheme}
                      aria-label={`Switch to ${resolvedTheme === 'dark' ? 'light' : 'dark'} mode`}
                    >
                      {resolvedTheme === 'dark' ? (
                        <Sun className="w-4 h-4 text-amber-400" aria-hidden="true" />
                      ) : (
                        <Moon className="w-4 h-4 text-indigo-500" aria-hidden="true" />
                      )}
                    </Button>
                    <LanguageSwitcher />
                  </div>

                  <Link to={tenantPath('/login')}>
                    <Button variant="light" size="sm" className="text-theme-secondary hover:text-theme-primary">
                      {t('auth.log_in')}
                    </Button>
                  </Link>
                  <Link to={tenantPath('/register')}>
                    <Button size="sm" className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium">
                      {t('auth.sign_up')}
                    </Button>
                  </Link>
                </>
              )}
            </div>
          </div>
        </div>

        {/* Search Overlay */}
        <SearchOverlay
          isOpen={isSearchOpen}
          onClose={() => setIsSearchOpen(false)}
        />
      </header>
    </>
  );
}

export default Navbar;
