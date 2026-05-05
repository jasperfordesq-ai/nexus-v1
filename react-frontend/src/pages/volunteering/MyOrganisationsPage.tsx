// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MyOrganisationsPage — Lists all volunteer organisations the user owns/manages.
 * Each card links to the org dashboard.
 *
 * API: GET /api/v2/volunteering/my-organisations
 */

import { useState, useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Chip, Spinner } from '@heroui/react';
import Building2 from 'lucide-react/icons/building-2';
import ArrowRight from 'lucide-react/icons/arrow-right';
import Plus from 'lucide-react/icons/plus';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks/usePageTitle';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { useTranslation } from 'react-i18next';

interface MyOrg {
  id: number;
  name: string;
  description: string | null;
  status: string;
  member_role: string;
  contact_email: string | null;
  website: string | null;
  balance?: number;
  auto_pay_enabled?: boolean;
  logo_url?: string | null;
}

export default function MyOrganisationsPage() {
  const { t } = useTranslation('volunteering');
  const { tenantPath } = useTenant();
  const [orgs, setOrgs] = useState<MyOrg[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const abortRef = useRef<AbortController | null>(null);

  usePageTitle(t('my_organisations_title', 'My Organisations'));

  useEffect(() => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setIsLoading(true);
    api.get<unknown>('/v2/volunteering/my-organisations')
      .then((res) => {
        if (controller.signal.aborted) return;
        if (res.success && res.data) {
          // respondWithData wraps in { data: { items: [...] } }
          const raw = res.data as { data?: { items?: unknown[] }; items?: unknown[] };
          const items = (raw.data?.items ?? raw.items ?? (Array.isArray(res.data) ? res.data : [])) as MyOrg[];
          setOrgs(items);
        }
      })
      .catch((err) => {
        if (controller.signal.aborted) return;
        logError('Failed to load my organisations', err);
      })
      .finally(() => {
        if (!controller.signal.aborted) setIsLoading(false);
      });

    return () => { abortRef.current?.abort(); };
  }, []);

  const managedOrgs = orgs.filter(o => ['owner', 'admin'].includes(o.member_role));
  const pendingOrgs = orgs.filter(o => o.status === 'pending');

  return (
    <div className="max-w-4xl mx-auto px-4 py-6 space-y-6">
      <Breadcrumbs
        items={[
          { label: t('breadcrumb_volunteering', 'Volunteering'), href: tenantPath('/volunteering') },
          { label: t('my_organisations', 'My Organisations') },
        ]}
      />

      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <Building2 className="w-7 h-7 text-rose-400" aria-hidden="true" />
            {t('my_organisations', 'My Organisations')}
          </h1>
          <p className="text-theme-muted mt-1">
            {t('my_organisations_subtitle', 'Manage your volunteer organisations, review applications, and pay volunteers.')}
          </p>
        </div>
        <Link to={tenantPath('/organisations/register')}>
          <Button
            className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
            startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
          >
            {t('register_organisation', 'Register Organisation')}
          </Button>
        </Link>
      </div>

      {isLoading ? (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      ) : managedOrgs.length === 0 && pendingOrgs.length === 0 ? (
        <GlassCard className="p-12 text-center">
          <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-rose-100 to-pink-100 dark:from-rose-900/30 dark:to-pink-900/30 flex items-center justify-center mx-auto mb-4">
            <Building2 className="w-8 h-8 text-rose-500" aria-hidden="true" />
          </div>
          <h2 className="text-xl font-semibold text-theme-primary mb-2">
            {t('my_organisations_none', 'No Organisations Yet')}
          </h2>
          <p className="text-theme-muted max-w-md mx-auto mb-6">
            {t('my_organisations_none_desc', 'Register a volunteer organisation to start posting opportunities, managing volunteers, and awarding time credits.')}
          </p>
          <Link to={tenantPath('/organisations/register')}>
            <Button
              className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
              startContent={<Plus className="w-4 h-4" />}
            >
              {t('register_organisation', 'Register Organisation')}
            </Button>
          </Link>
        </GlassCard>
      ) : (
        <div className="space-y-4">
          {/* Pending orgs */}
          {pendingOrgs.length > 0 && (
            <GlassCard className="p-4 border-amber-500/30">
              <p className="text-sm text-amber-400 font-medium mb-2">
                {t('my_organisations_pending', 'Pending Approval')}
              </p>
              {pendingOrgs.map((org) => (
                <div key={org.id} className="flex items-center gap-3 p-3 rounded-xl bg-theme-elevated">
                  <Building2 className="w-5 h-5 text-amber-400 flex-shrink-0" />
                  <div className="flex-1">
                    <p className="font-medium text-theme-primary">{org.name}</p>
                    <p className="text-xs text-theme-muted">{t('my_organisations_pending_desc', 'Awaiting admin approval')}</p>
                  </div>
                  <Chip size="sm" color="warning" variant="flat">{t('status_pending', 'Pending')}</Chip>
                </div>
              ))}
            </GlassCard>
          )}

          {/* Active orgs */}
          {managedOrgs.filter(o => o.status === 'approved' || o.status === 'active').map((org, idx) => (
            <motion.div
              key={org.id}
              initial={{ opacity: 0, y: 16 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: idx * 0.08 }}
            >
              <Link to={tenantPath(`/volunteering/org/${org.id}/dashboard`)} className="block">
                <GlassCard hoverable className="p-6 transition-all hover:border-rose-500/40">
                  <div className="flex flex-col sm:flex-row sm:items-center gap-4">
                    {/* Org icon */}
                    <div className="w-14 h-14 rounded-2xl bg-gradient-to-br from-rose-500 to-pink-600 flex items-center justify-center flex-shrink-0">
                      <Building2 className="w-7 h-7 text-white" aria-hidden="true" />
                    </div>

                    {/* Info */}
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2 flex-wrap">
                        <h2 className="text-lg font-bold text-theme-primary">{org.name}</h2>
                        <Chip size="sm" color="success" variant="flat">{t('status_active', 'Active')}</Chip>
                        <Chip size="sm" variant="flat" className="bg-theme-elevated text-theme-muted capitalize">{org.member_role}</Chip>
                      </div>
                      {org.description && (
                        <p className="text-sm text-theme-muted mt-1 line-clamp-2">{org.description}</p>
                      )}
                    </div>

                    {/* Stats + CTA */}
                    <div className="flex items-center gap-4 flex-shrink-0">
                      {org.balance !== undefined && (
                        <div className="text-center">
                          <p className="text-lg font-bold text-emerald-500">{t('hours_abbrev', { hours: org.balance })}</p>
                          <p className="text-xs text-theme-subtle">{t('wallet', 'Wallet')}</p>
                        </div>
                      )}
                      <Button
                        className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
                        endContent={<ArrowRight className="w-4 h-4" />}
                      >
                        {t('manage', 'Manage')}
                      </Button>
                    </div>
                  </div>
                </GlassCard>
              </Link>
            </motion.div>
          ))}
        </div>
      )}
    </div>
  );
}
