// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  Button,
  Card,
  CardBody,
  Chip,
  Select,
  SelectItem,
  Skeleton,
} from '@heroui/react';
import { useTranslation } from 'react-i18next';
import type { LucideIcon } from 'lucide-react';
import AlertTriangle from 'lucide-react/icons/alert-triangle';
import Bell from 'lucide-react/icons/bell';
import Calendar from 'lucide-react/icons/calendar';
import HandHeart from 'lucide-react/icons/hand-heart';
import HeartHandshake from 'lucide-react/icons/heart-handshake';
import Megaphone from 'lucide-react/icons/megaphone';
import Newspaper from 'lucide-react/icons/newspaper';
import ShoppingBag from 'lucide-react/icons/shopping-bag';
import Sparkles from 'lucide-react/icons/sparkles';
import Users from 'lucide-react/icons/users';

import { useTenant } from '@/contexts';
import { useToast } from '@/contexts/ToastContext';
import { useApi } from '@/hooks/useApi';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { SubRegionFilter } from '@/components/caring-community/SubRegionFilter';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

type DigestSource =
  | 'announcement'
  | 'project'
  | 'event'
  | 'vol_org'
  | 'care_provider'
  | 'marketplace'
  | 'safety_alert'
  | 'help_request'
  | 'feed_post';

interface DigestItem {
  id: string;
  source: DigestSource;
  title: string;
  summary: string;
  occurred_at: string | null;
  sub_region_id: number | null;
  audience_match_score: number;
  link_path: string | null;
}

interface DigestPrefs {
  enabled: boolean;
  cadence: 'off' | 'daily' | 'weekly';
  preferred_sub_region_id: number | null;
  opt_out_sources: DigestSource[];
  updated_at: number | null;
}

interface DigestResponse {
  items: DigestItem[];
  prefs: DigestPrefs;
  tenant_default_cadence: string;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const SOURCE_ICON: Record<DigestSource, LucideIcon> = {
  announcement: Megaphone,
  project: Sparkles,
  event: Calendar,
  vol_org: Users,
  care_provider: HeartHandshake,
  marketplace: ShoppingBag,
  safety_alert: AlertTriangle,
  help_request: HandHeart,
  feed_post: Newspaper,
};

function sourceColor(source: DigestSource): 'default' | 'primary' | 'warning' | 'danger' | 'success' {
  switch (source) {
    case 'safety_alert':
      return 'danger';
    case 'project':
    case 'announcement':
      return 'primary';
    case 'help_request':
      return 'warning';
    case 'event':
      return 'success';
    default:
      return 'default';
  }
}

function relativeLabel(iso: string | null, t: (key: string, opts?: Record<string, unknown>) => string): string {
  if (!iso) return '';
  const ts = new Date(iso).getTime();
  if (Number.isNaN(ts)) return '';
  const diffMs = Date.now() - ts;
  const hours = Math.round(diffMs / (1000 * 60 * 60));
  if (hours < 1) return t('time_ago_just_now');
  if (hours < 48) return t('time_ago_hours', { count: hours });
  const days = Math.round(hours / 24);
  return t('time_ago_days', { count: days });
}

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export function CivicDigestPage() {
  const { t } = useTranslation('civic_digest');
  const { tenantPath } = useTenant();
  const { showToast } = useToast();
  usePageTitle(t('page_title'));

  const { data, isLoading, error, refetch } = useApi<DigestResponse>(
    '/v2/caring-community/digest',
    { immediate: true },
  );

  const [cadence, setCadence] = useState<'off' | 'daily' | 'weekly'>('weekly');
  const [preferredSubRegionId, setPreferredSubRegionId] = useState<number | null>(null);
  const [saving, setSaving] = useState(false);

  // Sync local form state once data arrives
  useEffect(() => {
    if (data?.prefs) {
      setCadence(data.prefs.cadence);
      setPreferredSubRegionId(data.prefs.preferred_sub_region_id);
    }
  }, [data?.prefs]);

  const items = useMemo(() => data?.items ?? [], [data?.items]);

  const handleSave = async () => {
    setSaving(true);
    try {
      const payload: Record<string, unknown> = {
        cadence,
        preferred_sub_region_id: preferredSubRegionId,
      };
      const res = await api.put<{ prefs: DigestPrefs }>(
        '/v2/caring-community/digest/prefs',
        payload,
      );
      if (res.data?.prefs) {
        showToast(t('prefs_save_success'), 'success');
        await refetch();
      }
    } catch {
      showToast(t('prefs_save_error'), 'error');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-6">
      {/* Intro */}
      <Card>
        <CardBody className="gap-3 p-6">
          <div className="flex items-center gap-2">
            <Bell className="h-5 w-5 text-primary" aria-hidden />
            <h1 className="text-xl font-bold text-theme-primary">{t('intro_title')}</h1>
          </div>
          <p className="text-sm leading-relaxed text-theme-muted">{t('intro_body')}</p>
        </CardBody>
      </Card>

      {/* Loading state */}
      {isLoading && (
        <div className="space-y-3">
          {[0, 1, 2].map((n) => (
            <Card key={n}>
              <CardBody className="space-y-2 p-5">
                <Skeleton className="h-4 w-1/3 rounded-lg" />
                <Skeleton className="h-5 w-2/3 rounded-lg" />
                <Skeleton className="h-4 w-full rounded-lg" />
              </CardBody>
            </Card>
          ))}
        </div>
      )}

      {/* Error state */}
      {error && !isLoading && (
        <Card>
          <CardBody className="flex flex-row items-center gap-3 p-5 text-danger">
            <AlertTriangle className="h-5 w-5 shrink-0" aria-hidden />
            <p className="text-sm font-medium">{t('error_loading')}</p>
          </CardBody>
        </Card>
      )}

      {/* Empty state */}
      {!isLoading && !error && items.length === 0 && (
        <Card>
          <CardBody className="p-6 text-center">
            <p className="text-sm text-theme-muted">{t('empty_state')}</p>
          </CardBody>
        </Card>
      )}

      {/* Item list */}
      {!isLoading && items.length > 0 && (
        <ul className="space-y-3">
          {items.map((item) => {
            const Icon = SOURCE_ICON[item.source] ?? Newspaper;
            const sourceLabelKey = `source_${item.source}` as const;
            return (
              <li key={item.id}>
                <Card>
                  <CardBody className="gap-3 p-5">
                    <div className="flex items-start gap-3">
                      <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-default-100">
                        <Icon className="h-5 w-5 text-theme-primary" aria-hidden />
                      </div>
                      <div className="flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                          <Chip size="sm" color={sourceColor(item.source)} variant="flat">
                            {t(sourceLabelKey)}
                          </Chip>
                          {item.occurred_at && (
                            <span className="text-xs text-theme-muted">
                              {relativeLabel(item.occurred_at, t)}
                            </span>
                          )}
                        </div>
                        <h2 className="mt-1.5 text-base font-semibold leading-snug text-theme-primary">
                          {item.title}
                        </h2>
                        {item.summary && (
                          <p className="mt-1 text-sm leading-relaxed text-theme-muted">
                            {item.summary}
                          </p>
                        )}
                        {item.link_path && (
                          <div className="mt-2">
                            <Link
                              to={tenantPath(item.link_path)}
                              className="text-sm font-medium text-[var(--color-primary)] hover:underline"
                            >
                              {t('open_link')}
                            </Link>
                          </div>
                        )}
                      </div>
                    </div>
                  </CardBody>
                </Card>
              </li>
            );
          })}
        </ul>
      )}

      {/* Preferences */}
      <Card>
        <CardBody className="gap-4 p-6">
          <div className="flex items-center gap-2">
            <Sparkles className="h-5 w-5 text-primary" aria-hidden />
            <h2 className="text-lg font-semibold text-theme-primary">{t('prefs_title')}</h2>
          </div>

          <Select
            label={t('prefs_cadence_label')}
            selectedKeys={[cadence]}
            onChange={(e) => {
              const v = e.target.value;
              if (v === 'off' || v === 'daily' || v === 'weekly') {
                setCadence(v);
              }
            }}
            variant="bordered"
          >
            <SelectItem key="off">{t('prefs_cadence_off')}</SelectItem>
            <SelectItem key="daily">{t('prefs_cadence_daily')}</SelectItem>
            <SelectItem key="weekly">{t('prefs_cadence_weekly')}</SelectItem>
          </Select>

          <div className="space-y-2">
            <SubRegionFilter
              selectedId={preferredSubRegionId}
              onChange={setPreferredSubRegionId}
              label={t('prefs_sub_region_label')}
              className="flex-col items-start sm:flex-row sm:items-center"
            />
            <p className="text-xs leading-5 text-theme-muted">{t('prefs_sub_region_help')}</p>
          </div>

          <div className="flex justify-end">
            <Button
              color="primary"
              onPress={handleSave}
              isLoading={saving}
              isDisabled={saving}
            >
              {t('prefs_save')}
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default CivicDigestPage;
