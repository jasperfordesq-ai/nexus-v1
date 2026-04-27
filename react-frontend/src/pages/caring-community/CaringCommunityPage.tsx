// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

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
import MessageSquare from 'lucide-react/icons/message-square';
import ShieldCheck from 'lucide-react/icons/shield-check';
import Target from 'lucide-react/icons/target';
import Users from 'lucide-react/icons/users';
import Wallet from 'lucide-react/icons/wallet';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
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
  { key: 'messages', href: '/messages', icon: MessageSquare, module: 'messages' },
  { key: 'trust', href: '/verify-identity', icon: ShieldCheck },
];

function isVisible(
  item: ActionDef,
  hasFeature: (feature: keyof TenantFeatures) => boolean,
  hasModule: (module: keyof TenantModules) => boolean,
): boolean {
  if (item.feature && !hasFeature(item.feature)) return false;
  if (item.module && !hasModule(item.module)) return false;
  return true;
}

export function CaringCommunityPage() {
  const { t } = useTranslation('common');
  const { branding, hasFeature, hasModule, tenantPath } = useTenant();
  usePageTitle(t('caring_community.meta.title'));

  const visibleActions = primaryActions.filter((item) => isVisible(item, hasFeature, hasModule));
  const visibleCards = moduleCards.filter((item) => isVisible(item, hasFeature, hasModule));

  return (
    <>
      <PageMeta
        title={t('caring_community.meta.title')}
        description={t('caring_community.meta.description')}
        noIndex
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
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {visibleActions.map((item) => {
                  const Icon = item.icon;
                  return (
                    <Link key={item.key} to={tenantPath(item.href)}>
                      <Button
                        className="h-full min-h-24 w-full flex-col items-start justify-start gap-1 bg-theme-elevated px-4 py-4 text-theme-primary"
                        variant="flat"
                      >
                        <span className="flex items-center gap-2">
                          <Icon className="h-5 w-5 shrink-0" aria-hidden="true" />
                          <span className="text-left text-sm font-semibold">
                            {t(`caring_community.actions.${item.key}`)}
                          </span>
                        </span>
                        <span className="text-left text-xs font-normal leading-5 text-theme-muted">
                          {t(`caring_community.actions.${item.key}_sub`)}
                        </span>
                      </Button>
                    </Link>
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
                <Link key={item.key} to={tenantPath(item.href)} className="group">
                  <GlassCard className="h-full p-5 transition-transform group-hover:-translate-y-0.5">
                    <div className="flex items-start gap-4">
                      <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-teal-500/15">
                        <Icon className="h-5 w-5 text-teal-600 dark:text-teal-400" aria-hidden="true" />
                      </div>
                      <div className="min-w-0">
                        <h3 className="font-semibold text-theme-primary">
                          {t(`caring_community.modules.${item.key}.title`)}
                        </h3>
                        <p className="mt-1 text-sm leading-6 text-theme-muted">
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
