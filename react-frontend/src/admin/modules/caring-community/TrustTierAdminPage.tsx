// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Divider,
  Input,
  Spinner,
  Switch,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
} from '@heroui/react';
import Info from 'lucide-react/icons/info';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Save from 'lucide-react/icons/save';
import ShieldCheck from 'lucide-react/icons/shield-check';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { useApi } from '@/hooks/useApi';
import api from '@/lib/api';
import { useAdminPageMeta } from '../../AdminMetaContext';
import { PageHeader } from '../../components';

interface TierCriteria {
  hours_logged: number;
  reviews_received: number;
  identity_verified: boolean;
}

type TierName = 'member' | 'trusted' | 'verified' | 'coordinator';

interface TierConfigResponse {
  criteria: Record<TierName, TierCriteria>;
}

const TIER_ORDER: TierName[] = ['member', 'trusted', 'verified', 'coordinator'];
const TIER_REFERENCE_LEVELS = [0, 1, 2, 3, 4] as const;

interface TierRowProps {
  tierName: TierName;
  criteria: TierCriteria;
  onChange: (tierName: TierName, field: keyof TierCriteria, value: number | boolean) => void;
}

function TierRow({ tierName, criteria, onChange }: TierRowProps) {
  const { t } = useTranslation('admin');

  return (
    <div className="space-y-3">
      <div>
        <p className="font-semibold text-sm">{t(`admin.trust_tier.tiers.${tierName}.label`)}</p>
        <p className="text-xs text-foreground-500">{t(`admin.trust_tier.tiers.${tierName}.description`)}</p>
      </div>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <Input
          type="number"
          label={t('admin.trust_tier.fields.hours_logged')}
          value={String(criteria.hours_logged)}
          min={0}
          onValueChange={(v) => onChange(tierName, 'hours_logged', Math.max(0, parseInt(v || '0', 10)))}
          variant="bordered"
          size="sm"
        />
        <Input
          type="number"
          label={t('admin.trust_tier.fields.reviews_received')}
          value={String(criteria.reviews_received)}
          min={0}
          onValueChange={(v) => onChange(tierName, 'reviews_received', Math.max(0, parseInt(v || '0', 10)))}
          variant="bordered"
          size="sm"
        />
        <div className="flex items-center gap-2 pt-2">
          <Switch
            isSelected={criteria.identity_verified}
            onValueChange={(checked) => onChange(tierName, 'identity_verified', checked)}
            size="sm"
            aria-label={t('admin.trust_tier.fields.identity_verified')}
          />
          <span className="text-sm">{t('admin.trust_tier.fields.identity_verified')}</span>
        </div>
      </div>
    </div>
  );
}

export function TrustTierAdminPage() {
  const { t } = useTranslation('admin');
  usePageTitle(t('panel.sidebar.items.trust_tier'));
  useAdminPageMeta({
    title: t('admin.trust_tier.meta.title'),
    description: t('admin.trust_tier.meta.description'),
  });
  const { showToast } = useToast();

  const { data, isLoading, error, refetch } = useApi<TierConfigResponse>(
    '/v2/admin/caring-community/trust-tier/config',
    { immediate: true },
  );

  const [localCriteria, setLocalCriteria] = useState<Record<TierName, TierCriteria> | null>(null);
  const [saving, setSaving] = useState(false);
  const [recomputing, setRecomputing] = useState(false);

  const criteria = localCriteria ?? data?.criteria ?? null;

  function handleChange(tierName: TierName, field: keyof TierCriteria, value: number | boolean) {
    setLocalCriteria((prev) => {
      const base = prev ?? (data?.criteria ?? {} as Record<TierName, TierCriteria>);
      return {
        ...base,
        [tierName]: {
          ...base[tierName],
          [field]: value,
        },
      };
    });
  }

  async function handleSave() {
    if (!criteria) return;
    setSaving(true);
    try {
      await api.put('/v2/admin/caring-community/trust-tier/config', { criteria });
      showToast(t('admin.trust_tier.messages.saved'), 'success');
      setLocalCriteria(null);
      void refetch();
    } catch {
      showToast(t('admin.trust_tier.errors.save_failed'), 'error');
    } finally {
      setSaving(false);
    }
  }

  async function handleRecompute() {
    if (!window.confirm(t('admin.trust_tier.recompute_confirm'))) return;
    setRecomputing(true);
    try {
      const result = await api.post<{ updated: number }>(
        '/v2/admin/caring-community/trust-tier/recompute',
        {},
      );
      const updated = result.data?.updated ?? 0;
      showToast(t('admin.trust_tier.messages.recomputed', { count: updated }), 'success');
    } catch {
      showToast(t('admin.trust_tier.errors.recompute_failed'), 'error');
    } finally {
      setRecomputing(false);
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('admin.trust_tier.title')}
        description={t('admin.trust_tier.subtitle')}
        icon={<ShieldCheck size={20} />}
        actions={
          <div className="flex flex-wrap items-start gap-2 sm:justify-end">
            <div className="flex flex-col items-end gap-1">
              <Button
                color="default"
                variant="bordered"
                size="sm"
                startContent={<RefreshCw className="h-4 w-4" aria-hidden="true" />}
                onPress={() => void handleRecompute()}
                isLoading={recomputing}
                isDisabled={recomputing || saving}
              >
                {t('admin.trust_tier.actions.recompute')}
              </Button>
              <p className="max-w-xs text-right text-xs text-foreground-400">
                {t('admin.trust_tier.recompute_hint')}
              </p>
            </div>
            <Button
              color="primary"
              size="sm"
              startContent={<Save className="h-4 w-4" aria-hidden="true" />}
              onPress={() => void handleSave()}
              isLoading={saving}
              isDisabled={saving || recomputing || !localCriteria}
            >
              {t('admin.trust_tier.actions.save')}
            </Button>
          </div>
        }
      />

      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">{t('admin.trust_tier.about.title')}</p>
              <p className="text-default-600">{t('admin.trust_tier.about.body')}</p>
            </div>
          </div>
        </CardBody>
      </Card>

      {isLoading && (
        <div className="flex justify-center py-12">
          <Spinner size="lg" label={t('admin.trust_tier.loading')} />
        </div>
      )}

      {!isLoading && error && (
        <Card>
          <CardBody>
            <p className="text-danger text-sm">{t('admin.trust_tier.errors.load_failed')}</p>
          </CardBody>
        </Card>
      )}

      {!isLoading && !error && criteria && (
        <Card>
          <CardHeader>
            <p className="font-semibold text-sm">{t('admin.trust_tier.criteria.title')}</p>
          </CardHeader>
          <Divider />
          <CardBody className="space-y-6 p-6">
            {TIER_ORDER.map((tierName, i) => (
              <div key={tierName}>
                <TierRow
                  tierName={tierName}
                  criteria={criteria[tierName]}
                  onChange={handleChange}
                />
                {i < TIER_ORDER.length - 1 && <Divider className="mt-6" />}
              </div>
            ))}
          </CardBody>
        </Card>
      )}

      <Card>
        <CardHeader>
          <p className="font-semibold text-sm">{t('admin.trust_tier.reference.title')}</p>
        </CardHeader>
        <Divider />
        <CardBody className="p-0">
          <Table aria-label={t('admin.trust_tier.reference.aria')} removeWrapper>
            <TableHeader>
              <TableColumn>{t('admin.trust_tier.reference.columns.tier')}</TableColumn>
              <TableColumn>{t('admin.trust_tier.reference.columns.level')}</TableColumn>
              <TableColumn>{t('admin.trust_tier.reference.columns.color')}</TableColumn>
              <TableColumn>{t('admin.trust_tier.reference.columns.description')}</TableColumn>
              <TableColumn>{t('admin.trust_tier.reference.columns.unlocks')}</TableColumn>
            </TableHeader>
            <TableBody>
              {TIER_REFERENCE_LEVELS.map((level) => (
                <TableRow key={level}>
                  <TableCell className="font-mono text-xs">{level}</TableCell>
                  <TableCell className="font-medium">{t(`admin.trust_tier.reference.rows.${level}.name`)}</TableCell>
                  <TableCell className="text-foreground-500">{t(`admin.trust_tier.reference.rows.${level}.color`)}</TableCell>
                  <TableCell className="text-foreground-500">{t(`admin.trust_tier.reference.rows.${level}.description`)}</TableCell>
                  <TableCell className="text-foreground-500">{t(`admin.trust_tier.reference.rows.${level}.unlocks`)}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardBody>
      </Card>
    </div>
  );
}

export default TrustTierAdminPage;
