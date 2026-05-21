// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Credit Commons Node Configuration
 * Admin UI for configuring this tenant's CC node identity,
 * parent node, exchange rate, and validation window.
 */

import { useState, useCallback, useEffect } from 'react';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Input,
  Spinner,
  Divider,
  Chip,
} from '@heroui/react';
import Globe from 'lucide-react/icons/globe';
import Save from 'lucide-react/icons/save';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Link2 from 'lucide-react/icons/link-2';
import Hash from 'lucide-react/icons/hash';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { PageHeader } from '../../components';
import { useTranslation } from 'react-i18next';

interface CcNodeConfig {
  node_slug: string;
  display_name: string | null;
  currency_format: string;
  exchange_rate: number;
  validated_window: number;
  parent_node_url: string | null;
  parent_node_slug: string | null;
  last_hash: string | null;
  absolute_path: string[];
  stats: {
    trades: number;
    traders: number;
    volume: number;
    accounts: number;
    entries: number;
  };
}

export function CreditCommonsConfig() {
  const { t } = useTranslation('admin');
  usePageTitle(t('federation.cc_config_title'));
  const toast = useToast();

  const [config, setConfig] = useState<CcNodeConfig | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  // Form state
  const [nodeSlug, setNodeSlug] = useState('');
  const [displayName, setDisplayName] = useState('');
  const [currencyFormat, setCurrencyFormat] = useState('<quantity> hours');
  const [exchangeRate, setExchangeRate] = useState('1.0');
  const [validatedWindow, setValidatedWindow] = useState('300');
  const [parentNodeUrl, setParentNodeUrl] = useState('');
  const [parentNodeSlug, setParentNodeSlug] = useState('');

  const loadConfig = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get('/v2/admin/federation/cc-config');
      if (res.success) {
        const data = res.data as CcNodeConfig;
        setConfig(data);
        setNodeSlug(data.node_slug || '');
        setDisplayName(data.display_name || '');
        setCurrencyFormat(data.currency_format || '<quantity> hours');
        setExchangeRate(String(data.exchange_rate ?? 1.0));
        setValidatedWindow(String(data.validated_window ?? 300));
        setParentNodeUrl(data.parent_node_url || '');
        setParentNodeSlug(data.parent_node_slug || '');
      }
    } catch (err) {
      logError('CreditCommonsConfig.load', err);
      toast.error(t('federation.cc_config_load_failed'));
    }
    setLoading(false);
  }, [t, toast]);


  useEffect(() => {
    loadConfig();
  }, [loadConfig]);

  const handleSave = useCallback(async () => {
    if (!nodeSlug.trim()) {
      toast.error(t('federation.cc_node_slug_required'));
      return;
    }

    // Validate node slug format: 3-15 chars, lowercase alphanumeric + hyphens
    if (!/^[0-9a-z-]{3,15}$/.test(nodeSlug)) {
      toast.error(t('federation.cc_node_slug_invalid'));
      return;
    }

    setSaving(true);
    try {
      const res = await api.put('/v2/admin/federation/cc-config', {
        node_slug: nodeSlug,
        display_name: displayName || null,
        currency_format: currencyFormat,
        exchange_rate: parseFloat(exchangeRate) || 1.0,
        validated_window: parseInt(validatedWindow) || 300,
        parent_node_url: parentNodeUrl || null,
        parent_node_slug: parentNodeSlug || null,
      });

      if (res.success) {
        toast.success(t('federation.cc_config_saved'));
        loadConfig();
      } else {
        const errorMsg = (res as { error?: string }).error || t('federation.cc_config_save_failed');
        toast.error(errorMsg);
      }
    } catch (err) {
      logError('CreditCommonsConfig.save', err);
      toast.error(t('federation.cc_config_save_failed'));
    }
    setSaving(false);
  }, [nodeSlug, displayName, currencyFormat, exchangeRate, validatedWindow, parentNodeUrl, parentNodeSlug, toast, t, loadConfig]);

  if (loading) {
    return (
      <div>
        <PageHeader
          title={t('federation.cc_config_title')}
          description={t('federation.cc_config_desc')}
        />
        <div className="flex h-64 items-center justify-center">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('federation.cc_config_title')}
        description={t('federation.cc_config_desc')}
        actions={
          <div className="flex items-center gap-2">
            <Button variant="flat" size="sm" startContent={<RefreshCw size={16} />} onPress={loadConfig}>
              {t('federation.refresh')}
            </Button>
            <Button color="primary" size="sm" startContent={<Save size={16} />} isLoading={saving} onPress={handleSave}>
              {t('federation.save_changes')}
            </Button>
          </div>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Node Identity */}
        <Card>
          <CardHeader className="flex items-center gap-2 pb-2">
            <Globe size={18} />
            <h3 className="text-lg font-semibold">{t('federation.cc_node_identity')}</h3>
          </CardHeader>
          <CardBody className="gap-4">
            <Input
              label={t('federation.cc_node_slug')}
              description={t('federation.cc_node_slug_desc')}
              placeholder={t('federation.cc_node_slug_placeholder')}
              value={nodeSlug}
              onValueChange={setNodeSlug}
              isRequired
              maxLength={15}
            />
            <Input
              label={t('federation.cc_display_name')}
              placeholder={t('federation.cc_display_name_placeholder')}
              value={displayName}
              onValueChange={setDisplayName}
            />
            <Input
              label={t('federation.cc_currency_format')}
              description={t('federation.cc_currency_format_desc')}
              placeholder={t('federation.cc_currency_format_placeholder')}
              value={currencyFormat}
              onValueChange={setCurrencyFormat}
            />

            {config?.absolute_path && (
              <div className="pt-2">
                <p className="text-sm text-default-500 mb-1">{t('federation.cc_node_path')}</p>
                <div className="flex items-center gap-1">
                  {config.absolute_path.map((segment, i) => (
                    <span key={i} className="flex items-center gap-1">
                      {i > 0 && <span className="text-default-300">/</span>}
                      <Chip size="sm" variant="flat" color={i === config.absolute_path.length - 1 ? 'primary' : 'default'}>
                        {segment}
                      </Chip>
                    </span>
                  ))}
                </div>
              </div>
            )}
          </CardBody>
        </Card>

        {/* Parent Node */}
        <Card>
          <CardHeader className="flex items-center gap-2 pb-2">
            <Link2 size={18} />
            <h3 className="text-lg font-semibold">{t('federation.cc_parent_node')}</h3>
          </CardHeader>
          <CardBody className="gap-4">
            <Input
              label={t('federation.cc_parent_url')}
              description={t('federation.cc_parent_url_desc')}
              placeholder={t('federation.cc_parent_url_placeholder')}
              value={parentNodeUrl}
              onValueChange={setParentNodeUrl}
            />
            <Input
              label={t('federation.cc_parent_slug')}
              description={t('federation.cc_parent_slug_desc')}
              placeholder={t('federation.cc_parent_slug_placeholder')}
              value={parentNodeSlug}
              onValueChange={setParentNodeSlug}
            />
            <Input
              label={t('federation.cc_exchange_rate')}
              description={t('federation.cc_exchange_rate_desc')}
              placeholder={t('federation.cc_exchange_rate_placeholder')}
              type="number"
              step="0.01"
              min="0.01"
              value={exchangeRate}
              onValueChange={setExchangeRate}
            />
            <Input
              label={t('federation.cc_validated_window')}
              description={t('federation.cc_validated_window_desc')}
              placeholder={t('federation.cc_validated_window_placeholder')}
              type="number"
              min="30"
              max="86400"
              value={validatedWindow}
              onValueChange={setValidatedWindow}
            />
          </CardBody>
        </Card>

        {/* Hashchain Status */}
        <Card>
          <CardHeader className="flex items-center gap-2 pb-2">
            <Hash size={18} />
            <h3 className="text-lg font-semibold">{t('federation.cc_hashchain')}</h3>
          </CardHeader>
          <CardBody>
            <div className="space-y-3">
              <div>
                <p className="text-sm text-default-500">{t('federation.cc_last_hash')}</p>
                <code className="text-xs bg-default-100 px-2 py-1 rounded block mt-1 break-all">
                  {config?.last_hash || t('federation.cc_no_hash')}
                </code>
              </div>
              <Divider />
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-sm text-default-500">{t('federation.cc_total_trades')}</p>
                  <p className="text-lg font-semibold">{config?.stats?.trades ?? 0}</p>
                </div>
                <div>
                  <p className="text-sm text-default-500">{t('federation.cc_active_traders')}</p>
                  <p className="text-lg font-semibold">{config?.stats?.traders ?? 0}</p>
                </div>
                <div>
                  <p className="text-sm text-default-500">{t('federation.cc_volume')}</p>
                  <p className="text-lg font-semibold">{(config?.stats?.volume ?? 0).toFixed(2)}h</p>
                </div>
                <div>
                  <p className="text-sm text-default-500">{t('federation.cc_accounts')}</p>
                  <p className="text-lg font-semibold">{config?.stats?.accounts ?? 0}</p>
                </div>
              </div>
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export default CreditCommonsConfig;
