// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Card, CardBody, CardHeader } from '@/components/ui';
import AlertOctagon from 'lucide-react/icons/octagon-alert';
import Gauge from 'lucide-react/icons/gauge';
import HardDrive from 'lucide-react/icons/hard-drive';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import ShieldCheck from 'lucide-react/icons/shield-check';
import { useTranslation } from 'react-i18next';

export function OperatorGuide() {
  const { t } = useTranslation('admin_advanced', { keyPrefix: 'advanced.prerender.guide' });
  const items = [
    { title: t('bot_only.title'), body: t('bot_only.body') },
    { title: t('tenant_scope.title'), body: t('tenant_scope.body') },
    { title: t('dry_run.title'), body: t('dry_run.body') },
    { title: t('repair.title'), body: t('repair.body') },
  ];

  return (
    <Card className="mb-3">
      <CardBody className="gap-3">
        <div>
          <h2 className="text-lg font-semibold">{t('title')}</h2>
          <p className="text-sm text-muted">{t('intro')}</p>
        </div>
        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
          {items.map((item) => (
            <div key={item.title} className="rounded-md border border-divider p-3">
              <p className="text-sm font-medium">{item.title}</p>
              <p className="mt-1 text-sm text-muted">{item.body}</p>
            </div>
          ))}
        </div>
      </CardBody>
    </Card>
  );
}

export function GuidedWorkflows({ onSelect }: { onSelect: (tab: string) => void }) {
  const { t } = useTranslation('admin_advanced', { keyPrefix: 'advanced.prerender.workflows' });
  const workflows = [
    {
      key: 'refresh_one_tenant',
      icon: <RefreshCw size={16} />,
      action: () => onSelect('overview'),
    },
    {
      key: 'check_tenant_safety',
      icon: <ShieldCheck size={16} />,
      action: () => onSelect('tenant-safety'),
    },
    {
      key: 'find_stale_pages',
      icon: <Gauge size={16} />,
      action: () => onSelect('coverage'),
    },
    {
      key: 'inspect_cache',
      icon: <HardDrive size={16} />,
      action: () => onSelect('inventory'),
    },
    {
      key: 'investigate_failures',
      icon: <AlertOctagon size={16} />,
      action: () => onSelect('failures'),
    },
  ];

  return (
    <Card className="mb-3">
      <CardHeader>
        <div>
          <h2 className="text-lg font-semibold">{t('title')}</h2>
          <p className="text-sm text-muted">{t('description')}</p>
        </div>
      </CardHeader>
      <CardBody className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
        {workflows.map((workflow) => (
          <button
            key={workflow.key}
            type="button"
            onClick={workflow.action}
            className="rounded-md border border-divider p-3 text-left transition hover:border-accent hover:bg-accent/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
          >
            <span className="inline-flex items-center gap-2 text-sm font-semibold">
              {workflow.icon}
              {t(`${workflow.key}.title`)}
            </span>
            <span className="mt-1 block text-sm text-muted">
              {t(`${workflow.key}.body`)}
            </span>
            <span className="mt-3 inline-flex text-sm font-medium text-accent">
              {t('open')}
            </span>
          </button>
        ))}
      </CardBody>
    </Card>
  );
}
