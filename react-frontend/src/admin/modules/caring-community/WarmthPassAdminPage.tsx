// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useParams } from 'react-router-dom';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Divider,
  Input,
  Spinner,
} from '@heroui/react';
import CheckCircle from 'lucide-react/icons/check-circle';
import Clock from 'lucide-react/icons/clock';
import Info from 'lucide-react/icons/info';
import Search from 'lucide-react/icons/search';
import Shield from 'lucide-react/icons/shield';
import Star from 'lucide-react/icons/star';
import User from 'lucide-react/icons/user';
import XCircle from 'lucide-react/icons/x-circle';
import { PageHeader } from '../../components';
import { usePageTitle } from '@/hooks';
import api from '@/lib/api';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface WarmthPass {
  eligible: boolean;
  tier: number;
  tier_label: string;
  hours_logged: number;
  reviews_received: number;
  identity_verified: boolean;
  member_since: string | null;
  pass_active_since: string | null;
  tenant_name: string;
  member_name: string;
  categories: string[];
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function formatDate(iso: string | null, fallback: string): string {
  if (!iso) return fallback;
  return new Date(iso).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export function WarmthPassAdminPage() {
  const { t } = useTranslation('caring_community');
  usePageTitle(t('admin.warmth_pass.title'));
  const { userId: routeUserId } = useParams<{ userId?: string }>();
  const [userId, setUserId] = useState(routeUserId ?? '');
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<WarmthPass | null>(null);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  const lookupMember = useCallback(async (id: string) => {
    const trimmedId = id.trim();
    if (!trimmedId) return;
    setLoading(true);
    setResult(null);
    setErrorMsg(null);
    try {
      const res = await api.get<WarmthPass>(
        `/v2/admin/caring-community/warmth-pass/${trimmedId}`,
      );
      if (res.success && res.data) {
        setResult(res.data);
      } else {
        setErrorMsg(res.error ?? t('admin.warmth_pass.errors.no_data'));
      }
    } catch (err: unknown) {
      const msg =
        err instanceof Error ? err.message : t('admin.warmth_pass.errors.lookup_failed');
      setErrorMsg(msg);
    } finally {
      setLoading(false);
    }
  }, [t]);

  async function handleLookup() {
    await lookupMember(userId);
  }

  useEffect(() => {
    if (!routeUserId) return;
    setUserId(routeUserId);
    void lookupMember(routeUserId);
  }, [routeUserId, lookupMember]);

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('admin.warmth_pass.title')}
        subtitle={t('admin.warmth_pass.subtitle')}
        icon={<Shield className="h-5 w-5" aria-hidden="true" />}
      />

      {/* About card */}
      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">
                {t('admin.warmth_pass.about.title')}
              </p>
              <p className="text-default-600">
                {t('admin.warmth_pass.about.body')}
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Lookup form */}
      <Card>
        <CardHeader>
          <p className="font-semibold text-sm">{t('admin.warmth_pass.lookup.title')}</p>
        </CardHeader>
        <Divider />
        <CardBody className="p-5">
          <div className="flex items-end gap-3">
            <Input
              label={t('admin.warmth_pass.lookup.member_id')}
              placeholder={t('admin.warmth_pass.lookup.placeholder')}
              value={userId}
              onValueChange={setUserId}
              variant="bordered"
              className="max-w-xs"
              onKeyDown={(e) => {
                if (e.key === 'Enter') void handleLookup();
              }}
              startContent={<User className="h-4 w-4 text-default-400" aria-hidden="true" />}
            />
            <Button
              color="primary"
              onPress={() => void handleLookup()}
              isLoading={loading}
              isDisabled={!userId.trim() || loading}
              startContent={!loading && <Search className="h-4 w-4" aria-hidden="true" />}
            >
              {t('admin.warmth_pass.lookup.button')}
            </Button>
          </div>
        </CardBody>
      </Card>

      {/* Loading */}
      {loading && (
        <div className="flex justify-center py-10">
          <Spinner size="lg" label={t('admin.warmth_pass.loading')} />
        </div>
      )}

      {/* Error */}
      {!loading && errorMsg && (
        <Card>
          <CardBody>
            <div className="flex items-center gap-2 text-danger">
              <XCircle className="h-4 w-4 shrink-0" aria-hidden="true" />
              <p className="text-sm">{errorMsg}</p>
            </div>
          </CardBody>
        </Card>
      )}

      {/* Result */}
      {!loading && result && (
        <Card>
          <CardHeader>
            <div className="flex w-full flex-wrap items-center justify-between gap-3">
              <div className="flex items-center gap-3">
                <User className="h-5 w-5 text-default-500" aria-hidden="true" />
                <div>
                  <p className="font-bold text-base">{result.member_name}</p>
                  <p className="text-xs text-default-500">{result.tenant_name}</p>
                </div>
              </div>
              <Chip
                color={result.eligible ? 'success' : 'default'}
                variant="flat"
                size="sm"
              >
                {result.eligible ? t('admin.warmth_pass.eligible') : t('admin.warmth_pass.not_eligible')}
              </Chip>
            </div>
          </CardHeader>
          <Divider />
          <CardBody className="space-y-5 p-6">
            {/* Not eligible notice */}
            {result.tier < 2 && (
              <div className="rounded-lg border border-warning-200 bg-warning-50 p-3 dark:border-warning-800 dark:bg-warning-900/20">
                <p className="text-sm font-semibold text-warning-700 dark:text-warning-400">
                  {t('admin.warmth_pass.not_eligible_notice.title')}
                </p>
                <p className="mt-1 text-sm text-warning-700 dark:text-warning-400">
                  {t('admin.warmth_pass.not_eligible_notice.body')}
                </p>
              </div>
            )}

            {/* Tier */}
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <p className="text-xs font-semibold uppercase tracking-wide text-default-400 mb-1">
                  {t('admin.warmth_pass.fields.trust_tier')}
                </p>
                <Chip
                  size="md"
                  color={
                    result.tier >= 4 ? 'warning'
                    : result.tier === 3 ? 'success'
                    : result.tier === 2 ? 'warning'
                    : result.tier === 1 ? 'primary'
                    : 'default'
                  }
                  variant="flat"
                  className="capitalize font-semibold"
                >
                  {t('admin.warmth_pass.tier_chip', { label: result.tier_label, tier: result.tier })}
                </Chip>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase tracking-wide text-default-400 mb-1">
                  {t('admin.warmth_pass.fields.identity_verified')}
                </p>
                {result.identity_verified ? (
                  <div className="flex items-center gap-1.5 text-success">
                    <CheckCircle className="h-4 w-4" aria-hidden="true" />
                    <span className="text-sm font-semibold">{t('admin.warmth_pass.verified')}</span>
                  </div>
                ) : (
                  <div className="flex items-center gap-1.5 text-default-400">
                    <XCircle className="h-4 w-4" aria-hidden="true" />
                    <span className="text-sm">{t('admin.warmth_pass.not_verified')}</span>
                  </div>
                )}
              </div>
            </div>

            <Divider />

            {/* Stats */}
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
              <div className="rounded-lg border border-divider p-4">
                <div className="flex items-center gap-2 mb-1">
                  <Clock className="h-4 w-4 text-primary" aria-hidden="true" />
                  <p className="text-xs text-default-500">{t('admin.warmth_pass.fields.hours_logged')}</p>
                </div>
                <p className="text-2xl font-bold">{result.hours_logged}</p>
              </div>
              <div className="rounded-lg border border-divider p-4">
                <div className="flex items-center gap-2 mb-1">
                  <Star className="h-4 w-4 text-warning" aria-hidden="true" />
                  <p className="text-xs text-default-500">{t('admin.warmth_pass.fields.reviews_received')}</p>
                </div>
                <p className="text-2xl font-bold">{result.reviews_received}</p>
              </div>
            </div>

            <Divider />

            {/* Categories */}
            <div>
              <p className="text-xs font-semibold uppercase tracking-wide text-default-400 mb-2">
                {t('admin.warmth_pass.fields.help_categories')}
              </p>
              {result.categories.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                  {result.categories.map((cat) => (
                    <Chip key={cat} size="sm" variant="flat" color="primary">
                      {cat}
                    </Chip>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-default-400">{t('admin.warmth_pass.no_categories')}</p>
              )}
            </div>

            <Divider />

            {/* Dates */}
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <p className="text-xs font-semibold uppercase tracking-wide text-default-400 mb-1">
                  {t('admin.warmth_pass.fields.member_since')}
                </p>
                <p className="text-sm font-semibold">
                  {formatDate(result.member_since, t('admin.warmth_pass.empty_date'))}
                </p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase tracking-wide text-default-400 mb-1">
                  {t('admin.warmth_pass.fields.pass_active_since')}
                </p>
                <p className="text-sm font-semibold">
                  {formatDate(result.pass_active_since, t('admin.warmth_pass.empty_date'))}
                </p>
              </div>
            </div>
          </CardBody>
        </Card>
      )}
    </div>
  );
}

export default WarmthPassAdminPage;
