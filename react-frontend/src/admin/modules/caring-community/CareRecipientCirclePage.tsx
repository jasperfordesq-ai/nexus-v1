// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Divider,
  Input,
  Spinner,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
} from '@heroui/react';
import AlertTriangle from 'lucide-react/icons/alert-triangle';
import Clock from 'lucide-react/icons/clock';
import Heart from 'lucide-react/icons/heart';
import Info from 'lucide-react/icons/info';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import Users2 from 'lucide-react/icons/users';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { PageHeader, StatCard } from '../../components';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface SupportRelationship {
  id: number;
  supporter: { id: number; name: string; trust_tier: number };
  type: string;
  hours_logged: number;
  last_activity_at: string | null;
  status: string;
}

interface RecipientCircle {
  recipient: {
    id: number;
    name: string;
    trust_tier: number;
    member_since: string | null;
  };
  support_relationships: SupportRelationship[];
  total_hours_received: number;
  open_help_requests: number;
  safeguarding_flags: number;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function tierColor(tier: number): 'default' | 'warning' | 'success' | 'primary' {
  if (tier >= 4) return 'primary';
  if (tier >= 3) return 'success';
  if (tier >= 2) return 'warning';
  return 'default';
}

const TIER_KEYS = ['newcomer', 'member', 'trusted', 'verified', 'coordinator'] as const;

function fmtDate(iso: string | null, emptyValue: string): string {
  if (!iso) return emptyValue;
  return new Date(iso).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------

export default function CareRecipientCirclePage() {
  const { t } = useTranslation('caring_community');
  usePageTitle(t('admin.recipient_circle.meta_title'));

  const [userIdInput, setUserIdInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [circle, setCircle] = useState<RecipientCircle | null>(null);

  const handleLookup = useCallback(async () => {
    const userId = userIdInput.trim();
    if (!userId) return;

    setLoading(true);
    setError(null);
    setCircle(null);

    try {
      const res = await api.get<RecipientCircle>(
        `/v2/admin/caring-community/recipient/${userId}/circle`,
      );
      setCircle(res.data ?? null);
    } catch {
      setError(t('admin.recipient_circle.errors.load'));
    } finally {
      setLoading(false);
    }
  }, [t, userIdInput]);

  const tierLabel = useCallback(
    (tier: number) => {
      const key = TIER_KEYS[Math.min(tier, TIER_KEYS.length - 1)];
      return key ? t(`admin.recipient_circle.tiers.${key}`) : t('admin.recipient_circle.tiers.fallback', { tier });
    },
    [t],
  );

  const statusLabel = useCallback(
    (status: string) => t(`admin.recipient_circle.status.${status}`, { defaultValue: status.replace(/_/g, ' ') }),
    [t],
  );

  const relationshipTypeLabel = useCallback(
    (type: string) => t(`admin.recipient_circle.relationship_types.${type}`, { defaultValue: type.replace(/_/g, ' ') }),
    [t],
  );

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent) => {
      if (e.key === 'Enter') handleLookup();
    },
    [handleLookup],
  );

  const activeCount = circle?.support_relationships.filter((r) => r.status === 'active').length ?? 0;

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('admin.recipient_circle.title')}
        subtitle={t('admin.recipient_circle.subtitle')}
        icon={<Users2 size={20} />}
      />

      {/* Intro card */}
      <Card className="border border-primary/30 bg-primary-50/70 shadow-sm shadow-primary/10 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">{t('admin.recipient_circle.about.title')}</p>
              <p className="text-default-600">{t('admin.recipient_circle.about.body')}</p>
              <div className="space-y-0.5 pt-1 text-default-500">
                <p><strong>{t('admin.recipient_circle.about.trust_tiers_label')}</strong> {t('admin.recipient_circle.about.trust_tiers_body')}</p>
                <p><strong>{t('admin.recipient_circle.about.safeguarding_label')}</strong> {t('admin.recipient_circle.about.safeguarding_body')}</p>
                <p><strong>{t('admin.recipient_circle.about.privacy_label')}</strong> {t('admin.recipient_circle.about.privacy_body')}</p>
              </div>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Lookup bar */}
      <Card shadow="none" className="border border-divider/70 shadow-sm shadow-black/[0.03]">
        <CardBody>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
            <Input
              label={t('admin.recipient_circle.lookup.member_id')}
              placeholder={t('admin.recipient_circle.lookup.placeholder')}
              value={userIdInput}
              onValueChange={setUserIdInput}
              onKeyDown={handleKeyDown}
              variant="bordered"
              className="max-w-xs"
              type="number"
              min={1}
            />
            <Button
              color="primary"
              onPress={handleLookup}
              isLoading={loading}
              isDisabled={!userIdInput.trim()}
            >
              {t('admin.recipient_circle.lookup.button')}
            </Button>
          </div>
        </CardBody>
      </Card>

      {/* Loading */}
      {loading && (
        <div className="flex justify-center py-12">
          <Spinner size="lg" label={t('admin.recipient_circle.loading')} />
        </div>
      )}

      {/* Error */}
      {error && !loading && (
        <Card className="border border-danger/30">
          <CardBody className="flex flex-row items-center gap-3 text-danger">
            <AlertTriangle size={18} />
            <span className="text-sm">{error}</span>
          </CardBody>
        </Card>
      )}

      {/* Results */}
      {circle && !loading && (
        <>
          {/* Recipient profile card */}
          <Card shadow="none" className="border border-divider/70 shadow-sm shadow-black/[0.03]">
            <CardHeader className="pb-2">
              <span className="text-sm font-semibold text-default-600 uppercase tracking-wide">
                {t('admin.recipient_circle.recipient')}
              </span>
            </CardHeader>
            <CardBody className="pt-0">
              <div className="flex flex-wrap items-center gap-4">
                <div>
                  <p className="text-xl font-bold">{circle.recipient.name}</p>
                  <p className="text-sm text-default-500">
                    {t('admin.recipient_circle.member_since', {
                      date: fmtDate(circle.recipient.member_since, t('admin.common.empty_dash')),
                    })}
                  </p>
                </div>
                <Chip
                  color={tierColor(circle.recipient.trust_tier)}
                  variant="flat"
                  size="sm"
                >
                  {tierLabel(circle.recipient.trust_tier)}
                </Chip>
                {circle.safeguarding_flags > 0 && (
                  <Chip
                    color="danger"
                    variant="solid"
                    size="sm"
                    startContent={<ShieldAlert size={12} />}
                  >
                    {t('admin.recipient_circle.safeguarding_flags', { count: circle.safeguarding_flags })}
                  </Chip>
                )}
              </div>
            </CardBody>
          </Card>

          {/* Summary stat row */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <StatCard
              label={t('admin.recipient_circle.stats.total_hours')}
              value={circle.total_hours_received}
              icon={Clock}
              color="primary"
            />
            <StatCard
              label={t('admin.recipient_circle.stats.active_supporters')}
              value={activeCount}
              icon={Heart}
              color="success"
            />
            <StatCard
              label={t('admin.recipient_circle.stats.open_help_requests')}
              value={circle.open_help_requests}
              icon={AlertTriangle}
              color={circle.open_help_requests > 0 ? 'warning' : 'default'}
            />
          </div>

          <Divider />

          {/* Support Relationships table */}
          <Card shadow="none" className="border border-divider/70 shadow-sm shadow-black/[0.03]">
            <CardHeader>
              <span className="font-semibold text-sm">{t('admin.recipient_circle.relationships.title')}</span>
            </CardHeader>
            <CardBody className="p-0">
              {circle.support_relationships.length === 0 ? (
                <div className="flex flex-col items-center gap-2 py-10 text-default-500">
                  <Users2 size={36} className="opacity-30" />
                  <p className="text-sm">{t('admin.recipient_circle.relationships.empty')}</p>
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <Table
                    aria-label={t('admin.recipient_circle.relationships.aria')}
                    removeWrapper
                    classNames={{
                      th: 'bg-content2 text-xs font-semibold uppercase tracking-wide',
                    }}
                  >
                  <TableHeader>
                    <TableColumn>{t('admin.recipient_circle.relationships.supporter')}</TableColumn>
                    <TableColumn>{t('admin.recipient_circle.relationships.trust_tier')}</TableColumn>
                    <TableColumn>{t('admin.recipient_circle.relationships.type')}</TableColumn>
                    <TableColumn>{t('admin.recipient_circle.relationships.hours_logged')}</TableColumn>
                    <TableColumn>{t('admin.recipient_circle.relationships.last_activity')}</TableColumn>
                    <TableColumn>{t('admin.recipient_circle.relationships.status')}</TableColumn>
                  </TableHeader>
                  <TableBody>
                    {circle.support_relationships.map((rel) => (
                      <TableRow key={rel.id}>
                        <TableCell>
                          <div className="font-medium text-sm">{rel.supporter.name}</div>
                          <div className="text-xs text-default-400">{t('admin.recipient_circle.member_id_value', { id: rel.supporter.id })}</div>
                        </TableCell>
                        <TableCell>
                          <Chip
                            color={tierColor(rel.supporter.trust_tier)}
                            variant="flat"
                            size="sm"
                          >
                            {tierLabel(rel.supporter.trust_tier)}
                          </Chip>
                        </TableCell>
                        <TableCell className="text-sm">{relationshipTypeLabel(rel.type)}</TableCell>
                        <TableCell className="text-sm font-mono">
                          {rel.hours_logged.toLocaleString()}
                        </TableCell>
                        <TableCell className="text-sm text-default-500 whitespace-nowrap">
                          {fmtDate(rel.last_activity_at, t('admin.common.empty_dash'))}
                        </TableCell>
                        <TableCell>
                          <Chip
                            color={
                              rel.status === 'active'
                                ? 'success'
                                : rel.status === 'paused'
                                  ? 'warning'
                                  : 'default'
                            }
                            variant="flat"
                            size="sm"
                          >
                            {statusLabel(rel.status)}
                          </Chip>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                  </Table>
                </div>
              )}
            </CardBody>
          </Card>
        </>
      )}
    </div>
  );
}
