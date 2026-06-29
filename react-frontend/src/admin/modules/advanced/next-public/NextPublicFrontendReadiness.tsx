// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';
import ServerCog from 'lucide-react/icons/server-cog';
import RouteIcon from 'lucide-react/icons/route';
import ShieldCheck from 'lucide-react/icons/shield-check';
import TriangleAlert from 'lucide-react/icons/triangle-alert';
import Terminal from 'lucide-react/icons/terminal';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import { useTranslation } from 'react-i18next';
import { adminSettings, type NextPublicFrontendReadiness as Readiness } from '@/admin/api/adminApi';
import { PageHeader } from '@/admin/components/PageHeader';
import { usePageTitle } from '@/hooks/usePageTitle';
import { Button, Card, CardBody, CardHeader, Chip, Spinner } from '@/components/ui';

const statusColor = (status: string): 'success' | 'warning' | 'danger' | 'default' => {
  if (status === 'pass') return 'success';
  if (status === 'blocker') return 'warning';
  if (status === 'fail') return 'danger';
  return 'default';
};

export default function NextPublicFrontendReadiness() {
  const { t } = useTranslation('admin', { keyPrefix: 'advanced.next_public' });
  usePageTitle(t('title'));
  const [readiness, setReadiness] = useState<Readiness | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);

  const load = async () => {
    setLoading(true);
    setError(false);

    try {
      const response = await adminSettings.getNextPublicFrontendReadiness();
      setReadiness(response.data ?? null);
    } catch {
      setError(true);
      setReadiness(null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void load();
  }, []);

  return (
    <div>
      <PageHeader
        title={t('title')}
        description={t('description')}
        icon={<ServerCog size={22} aria-hidden="true" />}
        actions={(
          <Button size="sm" variant="secondary" onClick={() => void load()} startContent={<RefreshCw size={16} />}>
            {t('refresh')}
          </Button>
        )}
      />

      {loading && (
        <div className="flex min-h-48 items-center justify-center">
          <Spinner aria-label={t('loading')} />
        </div>
      )}

      {!loading && error && (
        <Card>
          <CardBody>
            <div className="flex items-center gap-3 text-danger">
              <TriangleAlert size={18} aria-hidden="true" />
              <span>{t('error')}</span>
            </div>
          </CardBody>
        </Card>
      )}

      {!loading && readiness && (
        <div className="space-y-5">
          <div className="grid gap-4 md:grid-cols-3">
            <StatusCard
              label={t('cards.mode')}
              value={readiness.mode === 'shadow' ? t('values.shadow_mode') : readiness.mode}
              tone="success"
            />
            <StatusCard
              label={t('cards.production_routing')}
              value={readiness.production_routing.active ? t('values.public_traffic_next') : t('values.public_traffic_not_next')}
              tone={readiness.production_routing.active ? 'danger' : 'success'}
            />
            <StatusCard
              label={t('cards.prerender')}
              value={readiness.prerender.fallback_retained ? t('values.prerender_retained') : t('values.prerender_not_retained')}
              tone={readiness.prerender.fallback_retained ? 'success' : 'danger'}
            />
          </div>

          <Card>
            <CardHeader className="flex items-center gap-2">
              <RouteIcon size={18} aria-hidden="true" />
              <h2 className="text-lg font-semibold">{t('route_ownership.title')}</h2>
            </CardHeader>
            <CardBody>
              <div className="grid gap-5 lg:grid-cols-2">
                <RouteList
                  title={t('route_ownership.next_public')}
                  items={readiness.manifest.public_routes.map((route) => route.pattern)}
                />
                <RouteList
                  title={t('route_ownership.vite_private')}
                  items={[
                    ...readiness.manifest.vite_private_prefixes,
                    ...readiness.manifest.vite_private_patterns,
                  ]}
                />
              </div>
            </CardBody>
          </Card>

          <div className="grid gap-4 lg:grid-cols-2">
            <Card>
              <CardHeader className="flex items-center gap-2">
                <ShieldCheck size={18} aria-hidden="true" />
                <h2 className="text-lg font-semibold">{t('safety.title')}</h2>
              </CardHeader>
              <CardBody>
                <div className="space-y-2">
                  {readiness.safety_checks.map((check) => (
                    <div key={check.key} className="flex items-center justify-between gap-3 rounded-md border border-divider px-3 py-2">
                      <span className="text-sm">{t(`safety_checks.${check.key}`)}</span>
                      <Chip size="sm" color={statusColor(check.status)} variant="soft">
                        {t(`statuses.${check.status}`)}
                      </Chip>
                    </div>
                  ))}
                </div>
              </CardBody>
            </Card>

            <Card>
              <CardHeader className="flex items-center gap-2">
                <Terminal size={18} aria-hidden="true" />
                <h2 className="text-lg font-semibold">{t('runtime.title')}</h2>
              </CardHeader>
              <CardBody>
                <dl className="space-y-3 text-sm">
                  <RuntimeRow label={t('runtime.compose_profile')} value={readiness.shadow_runtime.compose_profile} />
                  <RuntimeRow label={t('runtime.dev_command')} value={readiness.shadow_runtime.dev_command} />
                  <RuntimeRow label={t('runtime.build_command')} value={readiness.shadow_runtime.build_command} />
                  <RuntimeRow label={t('runtime.port_env')} value={readiness.shadow_runtime.host_port_env} />
                </dl>
              </CardBody>
            </Card>
          </div>

          <Card>
            <CardHeader>
              <h2 className="text-lg font-semibold">{t('cutover.title')}</h2>
            </CardHeader>
            <CardBody>
              <ol className="space-y-2 pl-5 text-sm text-muted">
                {readiness.cutover_step_keys.map((stepKey) => (
                  <li key={stepKey} className="list-decimal">
                    {t(`cutover_steps.${stepKey}`)}
                  </li>
                ))}
              </ol>
            </CardBody>
          </Card>
        </div>
      )}
    </div>
  );
}

function StatusCard({
  label,
  value,
  tone,
}: {
  label: string;
  value: string;
  tone: 'success' | 'danger';
}) {
  return (
    <Card>
      <CardBody>
        <div className="space-y-2">
          <div className="text-sm text-muted">{label}</div>
          <Chip color={tone} variant="soft">{value}</Chip>
        </div>
      </CardBody>
    </Card>
  );
}

function RouteList({ title, items }: { title: string; items: string[] }) {
  return (
    <section>
      <h3 className="mb-2 text-sm font-semibold text-muted">{title}</h3>
      <div className="flex flex-wrap gap-2">
        {items.map((item) => (
          <Chip key={item} size="sm" variant="flat">
            {item}
          </Chip>
        ))}
      </div>
    </section>
  );
}

function RuntimeRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="grid gap-1 sm:grid-cols-[10rem_1fr]">
      <dt className="text-muted">{label}</dt>
      <dd><code className="break-all rounded bg-surface-secondary px-2 py-1">{value}</code></dd>
    </div>
  );
}
