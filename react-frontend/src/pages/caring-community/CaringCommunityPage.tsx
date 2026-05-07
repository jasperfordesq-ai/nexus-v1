// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import type { ComponentType } from 'react';
import { Button, Chip } from '@heroui/react';
import ArrowRight from 'lucide-react/icons/arrow-right';
import Building2 from 'lucide-react/icons/building-2';
import Calendar from 'lucide-react/icons/calendar';
import FileText from 'lucide-react/icons/file-text';
import Globe from 'lucide-react/icons/globe';
import Handshake from 'lucide-react/icons/handshake';
import Heart from 'lucide-react/icons/heart';
import HeartHandshake from 'lucide-react/icons/heart-handshake';
import ListChecks from 'lucide-react/icons/list-checks';
import Megaphone from 'lucide-react/icons/megaphone';
import MessageSquare from 'lucide-react/icons/message-square';
import ShieldCheck from 'lucide-react/icons/shield-check';
import ShoppingBag from 'lucide-react/icons/shopping-bag';
import MoveRight from 'lucide-react/icons/move-right';
import Store from 'lucide-react/icons/store';
import Coins from 'lucide-react/icons/coins';
import PiggyBank from 'lucide-react/icons/piggy-bank';
import Gift from 'lucide-react/icons/gift';
import ArrowRightLeft from 'lucide-react/icons/arrow-right-left';
import UserRoundCheck from 'lucide-react/icons/user-round-check';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import Target from 'lucide-react/icons/target';
import Users from 'lucide-react/icons/users';
import Wallet from 'lucide-react/icons/wallet';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import {
  OnboardingChoiceModal,
  clearStoredOnboardingChoice,
  readStoredOnboardingChoice,
  type OnboardingChoice,
} from '@/components/caring-community/OnboardingChoiceModal';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import type { TenantFeatures, TenantModules } from '@/types/api';

interface ActionDef {
  key: string;
  href: string;
  icon: ComponentType<{ className?: string }>;
  feature?: keyof TenantFeatures;
  module?: keyof TenantModules;
}

const primaryActions: ActionDef[] = [
  // "Request Help" always visible within the hub — uses the dedicated low-friction flow
  { key: 'request_help', href: '/caring-community/request-help', icon: ListChecks },
  { key: 'offer_favour', href: '/caring-community/offer-favour', icon: HeartHandshake, feature: 'caring_community' },
  { key: 'offer_time', href: '/listings/create?type=offer', icon: Heart, module: 'listings' },
  { key: 'log_hours', href: '/volunteering?tab=hours', icon: Wallet, feature: 'volunteering' },
  { key: 'coordinate_org', href: '/volunteering/my-organisations', icon: Building2, feature: 'volunteering' },
  { key: 'my_relationships', href: '/caring-community/my-relationships', icon: Users, feature: 'caring_community' },
  { key: 'markt', href: '/caring-community/markt', icon: Store, feature: 'caring_community' },
  { key: 'loyalty_history', href: '/caring-community/loyalty/history', icon: Coins, feature: 'caring_community' },
  { key: 'future_care_fund', href: '/caring-community/future-care-fund', icon: PiggyBank, feature: 'caring_community' },
  { key: 'cover_care', href: '/caring-community/caregiver/cover', icon: UserRoundCheck, feature: 'caring_community' },
  { key: 'hour_gift', href: '/caring-community/hour-gift', icon: Gift, feature: 'caring_community' },
  { key: 'hour_transfer', href: '/caring-community/hour-transfer', icon: ArrowRightLeft, feature: 'caring_community' },
  { key: 'safeguarding_report', href: '/caring-community/safeguarding/report', icon: ShieldAlert, feature: 'caring_community' },
  { key: 'projects', href: '/caring-community/projects', icon: Megaphone, feature: 'caring_community' },
];

const moduleCards: ActionDef[] = [
  { key: 'timebank', href: '/listings', icon: ListChecks, module: 'listings' },
  { key: 'volunteering', href: '/volunteering', icon: Heart, feature: 'volunteering' },
  { key: 'organisations', href: '/organisations', icon: Building2, feature: 'organisations' },
  { key: 'events', href: '/events', icon: Calendar, feature: 'events' },
  { key: 'groups', href: '/groups', icon: Users, feature: 'groups' },
  { key: 'resources', href: '/resources', icon: FileText, feature: 'resources' },
  { key: 'goals', href: '/goals', icon: Target, feature: 'goals' },
  { key: 'federation', href: '/federation', icon: Globe, feature: 'federation' },
  { key: 'clubs', href: '/clubs', icon: ShoppingBag },
  { key: 'projects', href: '/caring-community/projects', icon: Megaphone, feature: 'caring_community' },
  { key: 'messages', href: '/messages', icon: MessageSquare, module: 'messages' },
  { key: 'trust', href: '/verify-identity', icon: ShieldCheck },
];

// Per-choice priority lists. Keys are surfaced first; remaining actions
// (when "show all" is active) follow in their original declaration order.
const CHOICE_PRIORITY: Record<Exclude<OnboardingChoice, 'browse'>, ReadonlyArray<string>> = {
  recipient: ['request_help', 'my_relationships', 'future_care_fund', 'safeguarding_report', 'markt', 'hour_gift'],
  helper: ['offer_favour', 'offer_time', 'log_hours', 'coordinate_org', 'cover_care', 'my_relationships'],
};

function isVisible(
  item: ActionDef,
  hasFeature: (feature: keyof TenantFeatures) => boolean,
  hasModule: (module: keyof TenantModules) => boolean,
): boolean {
  if (item.feature && !hasFeature(item.feature)) return false;
  if (item.module && !hasModule(item.module)) return false;
  return true;
}

function applyChoiceFilter(
  actions: ActionDef[],
  choice: OnboardingChoice | null,
  showAll: boolean,
): ActionDef[] {
  if (!choice || choice === 'browse' || showAll) return actions;
  const priority = CHOICE_PRIORITY[choice];
  const lookup = new Map(actions.map((a) => [a.key, a] as const));
  const ordered: ActionDef[] = [];
  for (const key of priority) {
    const match = lookup.get(key);
    if (match) {
      ordered.push(match);
      lookup.delete(key);
    }
  }
  return ordered;
}

export function CaringCommunityPage() {
  const { t } = useTranslation('common');
  const { branding, hasFeature, hasModule, tenant, tenantPath, tenantSlug } = useTenant();
  usePageTitle(t('caring_community.meta.title'));

  const onboardingTenantScope = tenant?.slug ?? tenantSlug ?? (tenant?.id ? String(tenant.id) : null);
  const [choice, setChoice] = useState<OnboardingChoice | null>(() => readStoredOnboardingChoice(onboardingTenantScope));
  const [modalOpen, setModalOpen] = useState<boolean>(false);
  const [showAll, setShowAll] = useState<boolean>(false);

  // Open the modal once on first mount when no stored choice exists.
  useEffect(() => {
    const storedChoice = readStoredOnboardingChoice(onboardingTenantScope);
    setChoice(storedChoice);
    if (storedChoice === null) {
      setModalOpen(true);
    }
  }, [onboardingTenantScope]);

  const handleChoice = useCallback((picked: OnboardingChoice) => {
    setChoice(picked);
    setShowAll(false);
    setModalOpen(false);
  }, []);

  const handleRefine = useCallback(() => {
    clearStoredOnboardingChoice(onboardingTenantScope);
    setChoice(null);
    setShowAll(false);
    setModalOpen(true);
  }, [onboardingTenantScope]);

  const handleToggleShowAll = useCallback(() => {
    setShowAll((v) => !v);
  }, []);

  const visibleActions = useMemo(() => {
    const base = primaryActions.filter((item) => isVisible(item, hasFeature, hasModule));
    return applyChoiceFilter(base, choice, showAll);
  }, [choice, hasFeature, hasModule, showAll]);

  const visibleCards = useMemo(
    () => moduleCards.filter((item) => isVisible(item, hasFeature, hasModule)),
    [hasFeature, hasModule],
  );

  const isFiltering = choice !== null && choice !== 'browse' && !showAll;

  return (
    <>
      <PageMeta
        title={t('caring_community.meta.title')}
        description={t('caring_community.meta.description')}
        noIndex
      />

      <OnboardingChoiceModal
        isOpen={modalOpen}
        onChoice={handleChoice}
        onClose={() => setModalOpen(false)}
        tenantScope={onboardingTenantScope}
      />

      <div className="space-y-6">
        {/* AG16 — warm welcome banner for elderly/non-technical users */}
        <div className="rounded-xl border border-emerald-200/60 bg-emerald-50/60 px-5 py-4 dark:border-emerald-800/40 dark:bg-emerald-950/30">
          <p className="text-base leading-7 text-emerald-800 dark:text-emerald-300">
            {t('caring_community.welcome_banner')}
          </p>
        </div>

        <section className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
          <GlassCard className="p-6 sm:p-8">
            <div className="flex flex-col gap-6">
              <div className="flex flex-wrap items-center gap-2">
                <Chip color="success" variant="flat" size="sm">
                  {t('caring_community.badge')}
                </Chip>
                <Chip color="primary" variant="flat" size="sm">
                  {branding.name}
                </Chip>
              </div>
              <div>
                <h1 className="text-3xl font-bold leading-tight text-theme-primary sm:text-4xl">
                  {t('caring_community.title')}
                </h1>
                <p className="mt-3 max-w-3xl text-base leading-8 text-theme-muted">
                  {t('caring_community.subtitle')}
                </p>
              </div>

              {(choice !== null && choice !== 'browse') && (
                <div className="flex flex-wrap items-center gap-2">
                  <Button size="sm" variant="flat" onPress={handleToggleShowAll}>
                    {isFiltering
                      ? t('caring_community:onboarding.show_all')
                      : t('caring_community:onboarding.show_recommended')}
                  </Button>
                  <Button size="sm" variant="light" onPress={handleRefine}>
                    {t('caring_community:onboarding.change_answer')}
                  </Button>
                </div>
              )}

              <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                {visibleActions.map((item) => {
                  const Icon = item.icon;
                  return (
                    <Button
                      key={item.key}
                      as={Link}
                      to={tenantPath(item.href)}
                      className="group h-auto min-h-28 w-full min-w-0 flex-col items-start justify-between gap-3 whitespace-normal bg-theme-elevated px-4 py-4 text-theme-primary transition-colors hover:bg-[var(--color-surface-hover)]"
                      variant="flat"
                    >
                      <span className="flex w-full min-w-0 items-start gap-3">
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                          <Icon className="h-5 w-5" aria-hidden="true" />
                        </span>
                        <span className="min-w-0 text-left text-sm font-semibold leading-5 break-words">
                          {t(`caring_community.actions.${item.key}`)}
                        </span>
                      </span>
                      <span className="flex w-full min-w-0 items-end justify-between gap-3">
                        <span className="block min-w-0 text-left text-xs font-normal leading-5 break-words text-theme-muted">
                          {t(`caring_community.actions.${item.key}_sub`)}
                        </span>
                        <MoveRight className="h-4 w-4 shrink-0 text-theme-subtle transition-transform group-hover:translate-x-0.5 group-hover:text-[var(--color-primary)]" aria-hidden="true" />
                      </span>
                    </Button>
                  );
                })}
              </div>
            </div>
          </GlassCard>

          <GlassCard className="p-6">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-500/15">
                <Handshake className="h-5 w-5 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
              </div>
              <div>
                <h2 className="font-semibold text-theme-primary">{t('caring_community.operating_model.title')}</h2>
                <p className="text-sm text-theme-muted">{t('caring_community.operating_model.subtitle')}</p>
              </div>
            </div>
            <div className="mt-5 space-y-3">
              {['trusted_requests', 'verified_hours', 'municipal_value'].map((key) => (
                <div key={key} className="rounded-lg border border-theme-default bg-theme-elevated p-3">
                  <p className="text-sm font-medium text-theme-primary">
                    {t(`caring_community.operating_model.${key}.title`)}
                  </p>
                  <p className="mt-1 text-xs leading-5 text-theme-muted">
                    {t(`caring_community.operating_model.${key}.description`)}
                  </p>
                </div>
              ))}
            </div>
          </GlassCard>
        </section>

        <section>
          <div className="mb-4 flex items-center justify-between gap-3">
            <div>
              <h2 className="text-xl font-semibold text-theme-primary">
                {t('caring_community.modules.title')}
              </h2>
              <p className="text-sm text-theme-muted">
                {t('caring_community.modules.subtitle')}
              </p>
            </div>
          </div>

          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {visibleCards.map((item) => {
              const Icon = item.icon;
              return (
                <Link
                  key={item.key}
                  to={tenantPath(item.href)}
                  className="group focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--color-background)]"
                  aria-label={t(`caring_community.modules.${item.key}.title`)}
                >
                  <GlassCard className="h-full p-5 transition-transform group-hover:-translate-y-0.5">
                    <div className="flex items-start gap-4">
                      <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-teal-500/15">
                        <Icon className="h-5 w-5 text-teal-600 dark:text-teal-400" aria-hidden="true" />
                      </div>
                      <div className="min-w-0">
                        <h3 className="break-words font-semibold text-theme-primary">
                          {t(`caring_community.modules.${item.key}.title`)}
                        </h3>
                        <p className="mt-1 text-sm leading-6 break-words text-theme-muted">
                          {t(`caring_community.modules.${item.key}.description`)}
                        </p>
                        <span className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-[var(--color-primary)]">
                          {t('caring_community.modules.open')}
                          <ArrowRight className="h-4 w-4" aria-hidden="true" />
                        </span>
                      </div>
                    </div>
                  </GlassCard>
                </Link>
              );
            })}
          </div>
        </section>
      </div>
    </>
  );
}

export default CaringCommunityPage;
