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
  if (status === 'blocked') return 'warning';
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
              <div className="mb-4 flex flex-wrap gap-2">
                <Chip size="sm" variant="soft">
                  {t('counts.public_routes', { count: readiness.manifest.route_counts.public_routes })}
                </Chip>
                <Chip size="sm" variant="soft">
                  {t('counts.private_prefixes', { count: readiness.manifest.route_counts.vite_private_prefixes })}
                </Chip>
                <Chip size="sm" variant="soft">
                  {t('counts.private_patterns', { count: readiness.manifest.route_counts.vite_private_patterns })}
                </Chip>
              </div>
              <div className="grid gap-5 lg:grid-cols-3">
                <RouteList
                  title={t('route_ownership.next_public')}
                  items={readiness.manifest.public_routes.map((route) => route.pattern)}
                />
                <RouteList
                  title={readiness.content_sources.source_of_truth}
                  items={readiness.content_sources.api_backed_routes.map((route) => `${route.method} ${route.endpoint}`)}
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

          <TenantResolutionCard readiness={readiness} />

          <EdgeCanaryCard readiness={readiness} />

          <CutoverArtifactInventoryCard readiness={readiness} />

          <CutoverEligibilityCard readiness={readiness} />

          <RouteBatchReadinessCard readiness={readiness} />

          <RemainingPublicRouteWorkCard readiness={readiness} />

          <PreCutoverDryRunsCard readiness={readiness} />

          <ManifestValidationCard readiness={readiness} />

          <RouteCutoverGatesCard readiness={readiness} />

          <OperatorPlaybookCard readiness={readiness} />

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
                {readiness.shadow_runtime.verification_commands.length > 0 && (
                  <ul className="mt-4 space-y-2">
                    {readiness.shadow_runtime.verification_commands.map((command) => (
                      <li key={command}>
                        <code className="block break-all rounded bg-surface-secondary px-2 py-1 text-xs">
                          {command}
                        </code>
                      </li>
                    ))}
                  </ul>
                )}
              </CardBody>
            </Card>
          </div>

          <Card>
            <CardHeader>
              <h2 className="text-lg font-semibold">{t('cutover.title')}</h2>
            </CardHeader>
            <CardBody>
              <div className="space-y-3">
                {readiness.cutover_gates.map((gate, index) => (
                  <div key={gate.key} className="rounded-md border border-divider px-3 py-3 text-sm">
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <div className="font-medium">
                          {index + 1}. {t(`cutover_steps.${gate.key}`)}
                        </div>
                        {gate.verification_commands.length > 0 && (
                          <div className="mt-2 space-y-1">
                            <div className="text-xs font-medium text-muted">{t('cutover.verification_commands')}</div>
                            {gate.verification_commands.map((command) => (
                              <code key={command} className="block break-all rounded bg-surface-secondary px-2 py-1 text-xs">
                                {command}
                              </code>
                            ))}
                          </div>
                        )}
                      </div>
                      <Chip size="sm" color={statusColor(gate.status)} variant="soft">
                        {t(`statuses.${gate.status}`)}
                      </Chip>
                    </div>
                    {gate.blockers.length > 0 && (
                      <div className="mt-2 flex flex-wrap gap-1">
                        {gate.blockers.map((blocker) => (
                          <Chip key={blocker} size="sm" color="warning" variant="soft">
                            {t(`cutover_gate_blockers.${blocker}`)}
                          </Chip>
                        ))}
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </CardBody>
          </Card>
        </div>
      )}
    </div>
  );
}

function CutoverEligibilityCard({ readiness }: { readiness: Readiness }) {
  const { t } = useTranslation('admin', { keyPrefix: 'advanced.next_public' });
  const eligibility = readiness.cutover_eligibility;

  return (
    <Card>
      <CardHeader className="flex items-center justify-between gap-3">
        <h2 className="text-lg font-semibold">{t('cutover_eligibility.title')}</h2>
        <Chip color={statusColor(eligibility.status)} variant="soft">
          {eligibility.eligible ? t('cutover_eligibility.eligible') : t('cutover_eligibility.blocked')}
        </Chip>
      </CardHeader>
      <CardBody>
        <div className="mb-4 flex flex-wrap gap-2">
          <Chip size="sm" color="success" variant="soft">
            {t(`production_effects.${eligibility.production_effect}`)}
          </Chip>
          <Chip size="sm" color={eligibility.activation_available ? 'danger' : 'success'} variant="soft">
            {eligibility.activation_available
              ? t('cutover_eligibility.activation_available')
              : t('cutover_eligibility.activation_unavailable')}
          </Chip>
          <Chip size="sm" color={eligibility.requires_explicit_cutover_instruction ? 'warning' : 'danger'} variant="soft">
            {t('edge_canary.explicit_instruction_required')}
          </Chip>
        </div>

        <div className="mb-4 flex flex-wrap gap-2">
          <Chip size="sm" variant="flat">
            {t('cutover_eligibility.public_routes', { count: eligibility.counts.public_routes })}
          </Chip>
          <Chip size="sm" color="success" variant="soft">
            {t('cutover_eligibility.api_backed_public_routes', { count: eligibility.counts.api_backed_public_routes })}
          </Chip>
          <Chip size="sm" color="warning" variant="soft">
            {t('cutover_eligibility.remaining_public_routes', { count: eligibility.counts.remaining_public_routes })}
          </Chip>
        </div>

        <div className="grid gap-3 lg:grid-cols-2">
          <div className="rounded-md border border-divider px-3 py-3 text-sm">
            <div className="mb-2 font-medium">{t('cutover_eligibility.blockers')}</div>
            <div className="flex flex-wrap gap-1">
              {eligibility.blockers.map((blocker) => (
                <Chip key={blocker} size="sm" color="warning" variant="soft">
                  {t(`cutover_eligibility_blockers.${blocker}`)}
                </Chip>
              ))}
            </div>
          </div>
          <div className="rounded-md border border-divider px-3 py-3 text-sm">
            <div className="mb-2 font-medium">{t('cutover_eligibility.required_actions')}</div>
            <div className="flex flex-wrap gap-1">
              {eligibility.required_actions.map((action) => (
                <Chip key={action} size="sm" color="warning" variant="soft">
                  {t(`cutover_eligibility_actions.${action}`)}
                </Chip>
              ))}
            </div>
          </div>
        </div>
      </CardBody>
    </Card>
  );
}

function TenantResolutionCard({ readiness }: { readiness: Readiness }) {
  const { t } = useTranslation('admin', { keyPrefix: 'advanced.next_public' });
  const tenantResolution = readiness.tenant_resolution;

  return (
    <Card>
      <CardHeader className="flex items-center justify-between gap-3">
        <h2 className="text-lg font-semibold">{t('tenant_resolution.title')}</h2>
        <Chip color={statusColor(tenantResolution.status)} variant="soft">
          {t(`statuses.${tenantResolution.status}`)}
        </Chip>
      </CardHeader>
      <CardBody>
        <div className="mb-4 grid gap-3 md:grid-cols-3">
          <RuntimeRow label={t('tenant_resolution.bootstrap_endpoint')} value={tenantResolution.bootstrap_endpoint} />
          <RuntimeRow label={t('tenant_resolution.source_of_truth')} value={tenantResolution.source_of_truth} />
          <RuntimeRow label={t('tenant_resolution.slug_parameter')} value={tenantResolution.shared_host_slug_parameter} />
        </div>
        <div className="mb-4">
          <Chip size="sm" color={tenantResolution.bootstrap_route_status === 'public' ? 'success' : 'warning'} variant="soft">
            {t(`tenant_resolution_route_statuses.${tenantResolution.bootstrap_route_status}`)}
          </Chip>
        </div>
        <div className="grid gap-3 lg:grid-cols-2">
          {tenantResolution.examples.map((example) => (
            <div key={example.key} className="rounded-md border border-divider px-3 py-3 text-sm">
              <div className="font-medium">{t(`tenant_resolution_examples.${example.key}`)}</div>
              <dl className="mt-2 space-y-2">
                <RuntimeRow label={t('tenant_resolution.request_host')} value={example.request_host} />
                <RuntimeRow label={t('tenant_resolution.request_path')} value={example.request_path} />
                <RuntimeRow label={t('tenant_resolution.bootstrap_request')} value={example.bootstrap_request} />
              </dl>
              <div className="mt-2 space-y-1">
                {example.headers.map((header) => (
                  <code key={header} className="block break-all rounded bg-surface-secondary px-2 py-1 text-xs">
                    {header}
                  </code>
                ))}
              </div>
            </div>
          ))}
        </div>
        <div className="mt-3 flex flex-wrap gap-2">
          <Chip size="sm" color={tenantResolution.custom_domain_origin_forwarding ? 'success' : 'warning'} variant="soft">
            {t('tenant_resolution.origin_forwarding')}
          </Chip>
          <Chip size="sm" color={tenantResolution.next_queries_database ? 'danger' : 'success'} variant="soft">
            {tenantResolution.next_queries_database ? t('tenant_resolution.next_queries_database') : t('tenant_resolution.no_next_database')}
          </Chip>
        </div>
      </CardBody>
    </Card>
  );
}

function RouteBatchReadinessCard({ readiness }: { readiness: Readiness }) {
  const { t } = useTranslation('admin', { keyPrefix: 'advanced.next_public' });

  return (
    <Card>
      <CardHeader>
        <h2 className="text-lg font-semibold">{t('route_batches.title')}</h2>
      </CardHeader>
      <CardBody>
        <div className="grid gap-3 lg:grid-cols-3">
          {readiness.route_batches.map((batch) => (
            <div key={batch.key} className="rounded-md border border-divider px-3 py-3 text-sm">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <div className="font-medium">{t(`route_batch_names.${batch.key}`)}</div>
                  <div className="mt-1 text-xs text-muted">
                    {t('route_batches.route_count', { count: batch.route_count })}
                  </div>
                </div>
                <Chip size="sm" color={statusColor(batch.status)} variant="soft">
                  {t(`statuses.${batch.status}`)}
                </Chip>
              </div>

              {batch.route_keys.length > 0 && (
                <div className="mt-3 flex max-h-28 flex-wrap gap-1 overflow-auto">
                  {batch.route_keys.map((routeKey) => (
                    <Chip key={routeKey} size="sm" variant="flat">
                      {routeKey}
                    </Chip>
                  ))}
                </div>
              )}

              {batch.blockers.length > 0 && (
                <div className="mt-3 flex flex-wrap gap-1">
                  {batch.blockers.map((blocker) => (
                    <Chip key={blocker} size="sm" color="warning" variant="soft">
                      {t(`route_batch_blockers.${blocker}`)}
                    </Chip>
                  ))}
                </div>
              )}

              {batch.verification_commands.length > 0 && (
                <div className="mt-3 space-y-1">
                  {batch.verification_commands.map((command) => (
                    <code key={command} className="block break-all rounded bg-surface-secondary px-2 py-1 text-xs">
                      {command}
                    </code>
                  ))}
                </div>
              )}
            </div>
          ))}
        </div>
      </CardBody>
    </Card>
  );
}

function RemainingPublicRouteWorkCard({ readiness }: { readiness: Readiness }) {
  const { t } = useTranslation('admin', { keyPrefix: 'advanced.next_public' });
  const remaining = readiness.remaining_public_route_work;
  const countLabel = (
    key: 'api_backed_routes' | 'public_routes' | 'remaining_routes' | 'unclassified_routes',
    count: number,
  ) => t(
    `remaining_route_work.${count === 1 ? key : `${key}_plural`}`,
    { count },
  );

  return (
    <Card>
      <CardHeader className="flex items-center justify-between gap-3">
        <h2 className="text-lg font-semibold">{t('remaining_route_work.title')}</h2>
        <div className="flex flex-wrap justify-end gap-2">
          <Chip size="sm" color="success" variant="soft">
            {t(`production_effects.${remaining.production_effect}`)}
          </Chip>
          <Chip size="sm" color={remaining.activation_available ? 'danger' : 'success'} variant="soft">
            {remaining.activation_available
              ? t('remaining_route_work.activation_available')
              : t('remaining_route_work.activation_unavailable')}
          </Chip>
        </div>
      </CardHeader>
      <CardBody>
        <div className="mb-4 flex flex-wrap gap-2">
          <Chip size="sm" variant="flat">
            {countLabel('public_routes', remaining.counts.public_routes)}
          </Chip>
          <Chip size="sm" color="success" variant="soft">
            {countLabel('api_backed_routes', remaining.counts.api_backed_public_routes)}
          </Chip>
          <Chip size="sm" color="warning" variant="soft">
            {countLabel('remaining_routes', remaining.counts.remaining_public_routes)}
          </Chip>
          <Chip
            size="sm"
            color={remaining.counts.unclassified_manifest_only_routes > 0 ? 'warning' : 'success'}
            variant="soft"
          >
            {countLabel('unclassified_routes', remaining.counts.unclassified_manifest_only_routes)}
          </Chip>
        </div>

        <div className="grid gap-3 lg:grid-cols-2 xl:grid-cols-4">
          {remaining.groups.map((group) => (
            <div key={group.key} className="rounded-md border border-divider px-3 py-3 text-sm">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <div className="font-medium">{t(`remaining_route_work_groups.${group.key}`)}</div>
                  <div className="mt-1 text-xs text-muted">
                    {t('remaining_route_work.route_count', { count: group.route_count })}
                  </div>
                </div>
                <Chip size="sm" color={statusColor(group.status)} variant="soft">
                  {t(`statuses.${group.status}`)}
                </Chip>
              </div>

              <Chip className="mt-3" size="sm" color="warning" variant="soft">
                {t(`remaining_route_work_reasons.${group.reason}`)}
              </Chip>

              {group.route_keys.length > 0 && (
                <div className="mt-3 flex max-h-28 flex-wrap gap-1 overflow-auto">
                  {group.route_keys.map((routeKey) => (
                    <Chip key={routeKey} size="sm" variant="flat">
                      {routeKey}
                    </Chip>
                  ))}
                </div>
              )}

              {group.required_actions.length > 0 && (
                <div className="mt-3 flex flex-wrap gap-1">
                  {group.required_actions.map((action) => (
                    <Chip key={action} size="sm" color="warning" variant="soft">
                      {t(`remaining_route_work_actions.${action}`)}
                    </Chip>
                  ))}
                </div>
              )}

              {group.verification_commands.length > 0 && (
                <div className="mt-3 space-y-1">
                  {group.verification_commands.map((command) => (
                    <code key={command} className="block break-all rounded bg-surface-secondary px-2 py-1 text-xs">
                      {command}
                    </code>
                  ))}
                </div>
              )}
            </div>
          ))}
        </div>

        {remaining.guardrails.length > 0 && (
          <div className="mt-4 flex flex-wrap gap-2">
            {remaining.guardrails.map((guardrail) => (
              <Chip key={guardrail} size="sm" color="success" variant="soft">
                {t(`remaining_route_work_guardrails.${guardrail}`)}
              </Chip>
            ))}
          </div>
        )}
      </CardBody>
    </Card>
  );
}

function PreCutoverDryRunsCard({ readiness }: { readiness: Readiness }) {
  const { t } = useTranslation('admin', { keyPrefix: 'advanced.next_public' });
  const dryRuns = readiness.pre_cutover_dry_runs;

  return (
    <Card>
      <CardHeader className="flex items-center justify-between gap-3">
        <h2 className="text-lg font-semibold">{t('dry_runs.title')}</h2>
        <div className="flex flex-wrap justify-end gap-2">
          <Chip size="sm" color="success" variant="soft">
            {t(`production_effects.${dryRuns.production_effect}`)}
          </Chip>
          <Chip size="sm" color={dryRuns.activation_available ? 'danger' : 'success'} variant="soft">
            {dryRuns.activation_available
              ? t('dry_runs.activation_available')
              : t('dry_runs.activation_unavailable')}
          </Chip>
          <Chip size="sm" color={dryRuns.requires_explicit_cutover_instruction ? 'warning' : 'danger'} variant="soft">
            {t('dry_runs.explicit_instruction_required')}
          </Chip>
        </div>
      </CardHeader>
      <CardBody>
        <div className="grid gap-3 lg:grid-cols-2 xl:grid-cols-3">
          {dryRuns.items.map((item) => (
            <div key={item.key} className="rounded-md border border-divider px-3 py-3 text-sm">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <div className="font-medium">{t(`dry_run_items.${item.key}`)}</div>
                  {item.commands.length > 0 && (
                    <div className="mt-2 space-y-1">
                      {item.commands.map((command) => (
                        <code key={command} className="block break-all rounded bg-surface-secondary px-2 py-1 text-xs">
                          {command}
                        </code>
                      ))}
                    </div>
                  )}
                </div>
                <Chip size="sm" color={statusColor(item.status)} variant="soft">
                  {t(`statuses.${item.status}`)}
                </Chip>
              </div>

              {item.route_keys.length > 0 && (
                <div className="mt-3 flex max-h-28 flex-wrap gap-1 overflow-auto">
                  {item.route_keys.map((routeKey) => (
                    <Chip key={routeKey} size="sm" variant="flat">
                      {routeKey}
                    </Chip>
                  ))}
                </div>
              )}

              {item.blockers.length > 0 && (
                <div className="mt-3 flex flex-wrap gap-1">
                  {item.blockers.map((blocker) => (
                    <Chip key={blocker} size="sm" color="warning" variant="soft">
                      {t(`dry_run_blockers.${blocker}`)}
                    </Chip>
                  ))}
                </div>
              )}

              {item.notes.length > 0 && (
                <div className="mt-3 flex flex-wrap gap-1">
                  {item.notes.map((note) => (
                    <Chip key={note} size="sm" color="success" variant="soft">
                      {t(`dry_run_notes.${note}`)}
                    </Chip>
                  ))}
                </div>
              )}
            </div>
          ))}
        </div>
      </CardBody>
    </Card>
  );
}

function CutoverArtifactInventoryCard({ readiness }: { readiness: Readiness }) {
  const { t } = useTranslation('admin', { keyPrefix: 'advanced.next_public' });
  const inventory = readiness.cutover_artifacts;

  return (
    <Card>
      <CardHeader className="flex items-center justify-between gap-3">
        <h2 className="text-lg font-semibold">{t('artifacts.title')}</h2>
        <Chip color={inventory.activation_available ? 'danger' : 'success'} variant="soft">
          {inventory.activation_available ? t('artifacts.activation_available') : t('artifacts.no_activation_control')}
        </Chip>
      </CardHeader>
      <CardBody>
        <div className="mb-4 flex flex-wrap gap-2">
          <Chip size="sm" color="success" variant="soft">
            {t(`production_effects.${inventory.production_effect}`)}
          </Chip>
        </div>

        <div className="grid gap-3 lg:grid-cols-2">
          {inventory.items.map((item) => (
            <div key={item.key} className="rounded-md border border-divider px-3 py-3 text-sm">
              <div className="mb-2 font-medium">{t(`artifact_items.${item.key}`)}</div>
              <RuntimeRow label={t('artifacts.path')} value={item.path} />
              <div className="mt-3 flex flex-wrap gap-2">
                <Chip size="sm" color={item.exists ? 'success' : 'warning'} variant="soft">
                  {item.exists ? t('artifacts.present') : t('artifacts.missing')}
                </Chip>
                <Chip size="sm" variant="soft">
                  {t(`artifact_categories.${item.category}`)}
                </Chip>
                <Chip size="sm" color={item.production_effect === 'none' ? 'success' : 'warning'} variant="soft">
                  {t(`production_effects.${item.production_effect}`)}
                </Chip>
              </div>
            </div>
          ))}
        </div>

        {inventory.required_commands.length > 0 && (
          <div className="mt-4 space-y-2">
            <div className="text-sm font-medium">{t('artifacts.required_commands')}</div>
            {inventory.required_commands.map((command) => (
              <div key={command.key} className="rounded-md border border-divider px-3 py-2">
                <code className="block break-all text-xs">{command.command}</code>
                {command.required_before_cutover && (
                  <Chip className="mt-2" size="sm" color="warning" variant="soft">
                    {t('artifacts.required_before_cutover')}
                  </Chip>
                )}
              </div>
            ))}
          </div>
        )}
      </CardBody>
    </Card>
  );
}

function EdgeCanaryCard({ readiness }: { readiness: Readiness }) {
  const { t } = useTranslation('admin', { keyPrefix: 'advanced.next_public' });
  const edgeCanary = readiness.edge_canary;

  return (
    <Card>
      <CardHeader className="flex items-center justify-between gap-3">
        <h2 className="text-lg font-semibold">{t('edge_canary.title')}</h2>
        <Chip color={statusColor(edgeCanary.status)} variant="soft">
          {t(`statuses.${edgeCanary.status}`)}
        </Chip>
      </CardHeader>
      <CardBody>
        <div className="mb-4 grid gap-3 md:grid-cols-3">
          <RuntimeRow label={t('edge_canary.edge')} value={t(`edge_canary_edges.${edgeCanary.edge}`)} />
          <RuntimeRow label={t('edge_canary.routing_flag')} value={edgeCanary.routing_flag} />
          <RuntimeRow label={t('edge_canary.route_file_status')} value={t(`edge_canary_route_file_statuses.${edgeCanary.route_file_status}`)} />
        </div>
        <div className="mb-4 rounded-md border border-divider px-3 py-3 text-sm">
          <RuntimeRow label={t('edge_canary.config_template')} value={edgeCanary.config_template.path} />
          <div className="mt-3 flex flex-wrap gap-2">
            <Chip size="sm" color={edgeCanary.config_template.exists ? 'success' : 'warning'} variant="soft">
              {edgeCanary.config_template.exists ? t('edge_canary.template_exists') : t('edge_canary.template_missing')}
            </Chip>
            <Chip size="sm" color={edgeCanary.config_template.example_only ? 'success' : 'danger'} variant="soft">
              {edgeCanary.config_template.example_only ? t('edge_canary.example_only') : t('edge_canary.not_example_only')}
            </Chip>
            <Chip size="sm" color={edgeCanary.config_template.included_by_deploy ? 'danger' : 'success'} variant="soft">
              {edgeCanary.config_template.included_by_deploy ? t('edge_canary.included_by_deploy') : t('edge_canary.not_included_by_deploy')}
            </Chip>
          </div>
          <div className="mt-3 flex flex-wrap gap-1">
            {edgeCanary.config_template.required_review_steps.map((step) => (
              <Chip key={step} size="sm" color="warning" variant="soft">
                {t(`edge_canary_review_steps.${step}`)}
              </Chip>
            ))}
          </div>
        </div>

        <div className="mb-4 rounded-md border border-divider px-3 py-3 text-sm">
          <div className="mb-3 flex items-center justify-between gap-3">
            <div className="font-medium">{t('edge_canary.route_audit_title')}</div>
            <Chip size="sm" color={statusColor(edgeCanary.route_audit.status)} variant="soft">
              {t(`statuses.${edgeCanary.route_audit.status}`)}
            </Chip>
          </div>
          <div className="flex flex-wrap gap-2">
            <Chip size="sm" color="success" variant="soft">
              {t('edge_canary.exact_path_count', { count: edgeCanary.route_audit.exact_path_count })}
            </Chip>
            <Chip size="sm" color={edgeCanary.route_audit.public_only ? 'success' : 'danger'} variant="soft">
              {edgeCanary.route_audit.public_only ? t('edge_canary.public_only') : t('edge_canary.not_public_only')}
            </Chip>
            <Chip size="sm" color={edgeCanary.route_audit.private_collisions.length === 0 ? 'success' : 'danger'} variant="soft">
              {edgeCanary.route_audit.private_collisions.length === 0 ? t('edge_canary.no_private_collisions') : t('edge_canary.private_collisions')}
            </Chip>
          </div>
          {edgeCanary.route_audit.template_paths.length > 0 && (
            <div className="mt-3 flex max-h-28 flex-wrap gap-1 overflow-auto">
              {edgeCanary.route_audit.template_paths.map((path) => (
                <Chip key={path} size="sm" variant="flat">
                  {path}
                </Chip>
              ))}
            </div>
          )}
          {(edgeCanary.route_audit.unmatched_template_paths.length > 0 || edgeCanary.route_audit.unsupported_rules.length > 0) && (
            <div className="mt-3 flex flex-wrap gap-1">
              {edgeCanary.route_audit.unmatched_template_paths.map((path) => (
                <Chip key={`unmatched-${path}`} size="sm" color="danger" variant="soft">
                  {t('edge_canary.unmatched_template_path', { path })}
                </Chip>
              ))}
              {edgeCanary.route_audit.unsupported_rules.map((rule) => (
                <Chip key={`unsupported-${rule}`} size="sm" color="danger" variant="soft">
                  {t('edge_canary.unsupported_rule', { rule })}
                </Chip>
              ))}
            </div>
          )}
        </div>

        <div className="mb-4 flex flex-wrap gap-2">
          <Chip size="sm" color={edgeCanary.routing_flag_enabled ? 'danger' : 'success'} variant="soft">
            {edgeCanary.routing_flag_enabled ? t('edge_canary.routing_flag_on') : t('edge_canary.routing_flag_off')}
          </Chip>
          <Chip size="sm" color={edgeCanary.activation_available ? 'danger' : 'success'} variant="soft">
            {edgeCanary.activation_available ? t('edge_canary.activation_available') : t('edge_canary.no_activation_control')}
          </Chip>
          <Chip size="sm" color={edgeCanary.preview_only ? 'success' : 'warning'} variant="soft">
            {edgeCanary.preview_only ? t('edge_canary.preview_only') : t('edge_canary.not_preview_only')}
          </Chip>
          <Chip size="sm" color={edgeCanary.reviewed_config_required ? 'warning' : 'danger'} variant="soft">
            {t('edge_canary.reviewed_config_required')}
          </Chip>
          <Chip size="sm" color={edgeCanary.requires_explicit_cutover_instruction ? 'warning' : 'danger'} variant="soft">
            {t('edge_canary.explicit_instruction_required')}
          </Chip>
        </div>

        <div className="flex flex-wrap gap-2">
          {edgeCanary.guardrails.map((guardrail) => (
            <Chip key={guardrail} size="sm" color="warning" variant="soft">
              {t(`edge_canary_guardrails.${guardrail}`)}
            </Chip>
          ))}
        </div>
      </CardBody>
    </Card>
  );
}

function OperatorPlaybookCard({ readiness }: { readiness: Readiness }) {
  const { t } = useTranslation('admin', { keyPrefix: 'advanced.next_public' });
  const playbook = readiness.operator_playbook;

  return (
    <Card>
      <CardHeader>
        <h2 className="text-lg font-semibold">{t('playbook.title')}</h2>
      </CardHeader>
      <CardBody>
        <div className="mb-4 flex flex-wrap gap-2">
          <Chip size="sm" color={playbook.activation_available ? 'danger' : 'success'} variant="soft">
            {playbook.activation_available ? t('playbook.activation_available') : t('playbook.no_activation_control')}
          </Chip>
          <Chip size="sm" color="warning" variant="soft">
            {t('playbook.explicit_instruction_required')}
          </Chip>
          <Chip size="sm" color="success" variant="soft">
            {t('playbook.no_production_effect')}
          </Chip>
        </div>

        <div className="space-y-3">
          {playbook.stages.map((stage, index) => (
            <div key={stage.key} className="rounded-md border border-divider px-3 py-3 text-sm">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <div className="font-medium">
                    {index + 1}. {t(`playbook_stages.${stage.key}`)}
                  </div>
                  {stage.commands.length > 0 && (
                    <div className="mt-2 space-y-1">
                      {stage.commands.map((command) => (
                        <code key={command} className="block break-all rounded bg-surface-secondary px-2 py-1 text-xs">
                          {command}
                        </code>
                      ))}
                    </div>
                  )}
                </div>
                <Chip size="sm" color={statusColor(stage.status)} variant="soft">
                  {t(`statuses.${stage.status}`)}
                </Chip>
              </div>
              {stage.notes.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-1">
                  {stage.notes.map((note) => (
                    <Chip key={note} size="sm" color="warning" variant="soft">
                      {t(`playbook_notes.${note}`)}
                    </Chip>
                  ))}
                </div>
              )}
            </div>
          ))}
        </div>
      </CardBody>
    </Card>
  );
}

function RouteCutoverGatesCard({ readiness }: { readiness: Readiness }) {
  const { t } = useTranslation('admin', { keyPrefix: 'advanced.next_public' });

  return (
    <Card>
      <CardHeader>
        <h2 className="text-lg font-semibold">{t('route_gates.title')}</h2>
      </CardHeader>
      <CardBody>
        <div className="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
          {readiness.manifest.route_readiness.map((route) => (
            <div key={route.routeKey} className="rounded-md border border-divider px-3 py-2 text-sm">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <div className="font-medium">{route.routeKey}</div>
                  <code className="mt-1 block break-all text-xs text-muted">{route.pattern}</code>
                  <code className="mt-1 block break-all text-xs">{route.content_source}</code>
                </div>
                <Chip size="sm" color={statusColor(route.status)} variant="soft">
                  {t(`statuses.${route.status}`)}
                </Chip>
              </div>
              {route.blockers.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-1">
                  {route.blockers.map((blocker) => (
                    <Chip key={blocker} size="sm" color="warning" variant="soft">
                      {t(`route_gate_blockers.${blocker}`)}
                    </Chip>
                  ))}
                </div>
              )}
            </div>
          ))}
        </div>
      </CardBody>
    </Card>
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

function ManifestValidationCard({ readiness }: { readiness: Readiness }) {
  const { t } = useTranslation('admin', { keyPrefix: 'advanced.next_public' });
  const validation = readiness.manifest.validation;
  const passed = validation.status === 'pass';

  return (
    <Card>
      <CardHeader className="flex items-center justify-between gap-3">
        <h2 className="text-lg font-semibold">{t('validation.title')}</h2>
        <Chip color={passed ? 'success' : 'warning'} variant="soft">
          {passed ? t('validation.passed') : t('validation.blocked')}
        </Chip>
      </CardHeader>
      <CardBody>
        <div className="grid gap-3 md:grid-cols-3">
          <RuntimeCheck label={t('validation.lockfile')} ok={readiness.app.lockfile_exists} />
          <RuntimeCheck label={t('validation.package_scripts')} ok={Object.values(readiness.app.package_scripts).every(Boolean)} />
          <RuntimeCheck label={t('validation.compose_profile')} ok={readiness.shadow_runtime.compose_profile_configured} />
        </div>

        {validation.issues.length > 0 && (
          <div className="mt-4 space-y-2">
            {validation.issues.map((issue) => (
              <div key={`${issue.code}-${issue.context}`} className="rounded-md border border-warning/40 bg-warning/10 px-3 py-2 text-sm">
                <span className="font-medium">{t(`validation_issues.${issue.code}`)}</span>
                <code className="ml-2 text-xs">{issue.context}</code>
              </div>
            ))}
          </div>
        )}
      </CardBody>
    </Card>
  );
}

function RuntimeCheck({ label, ok }: { label: string; ok: boolean }) {
  const { t } = useTranslation('admin', { keyPrefix: 'advanced.next_public' });

  return (
    <div className="flex items-center justify-between gap-3 rounded-md border border-divider px-3 py-2 text-sm">
      <span>{label}</span>
      <Chip size="sm" color={ok ? 'success' : 'warning'} variant="soft">
        {ok ? t('statuses.pass') : t('statuses.blocker')}
      </Chip>
    </div>
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
