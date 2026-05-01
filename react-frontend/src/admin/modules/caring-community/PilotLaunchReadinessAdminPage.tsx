// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Divider,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Progress,
  Spinner,
  Tooltip,
} from '@heroui/react';
import CheckCircle2 from 'lucide-react/icons/circle-check';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import ChevronRight from 'lucide-react/icons/chevron-right';
import Rocket from 'lucide-react/icons/rocket';
import ClipboardCheck from 'lucide-react/icons/clipboard-check';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader } from '../../components';

type SectionStatus = 'ready' | 'needs_review' | 'not_started' | 'blocked';

interface ReadinessSection {
  key: string;
  label: string;
  status: SectionStatus;
  summary: string;
  admin_path: string;
  last_updated_at: string | null;
  missing: string[];
  extra?: Record<string, unknown>;
}

interface ReadinessLaunchedState {
  launched_at: string;
  launched_by_id: number;
}

interface ReadinessBlocker {
  key: string;
  label: string;
  status: string;
}

interface ReadinessReport {
  generated_at: string;
  overall: {
    status: SectionStatus;
    ready_section_count: number;
    total_section_count: number;
    summary: string;
  };
  sections: ReadinessSection[];
  isolated_node_required: boolean;
  can_launch: boolean;
  blockers: ReadinessBlocker[];
  launched: ReadinessLaunchedState | null;
}

const STATUS_COLORS: Record<SectionStatus, 'success' | 'warning' | 'default' | 'danger'> = {
  ready: 'success',
  needs_review: 'warning',
  not_started: 'default',
  blocked: 'danger',
};

const STATUS_LABELS: Record<SectionStatus, string> = {
  ready: 'Ready',
  needs_review: 'Needs review',
  not_started: 'Not started',
  blocked: 'Blocked',
};

function statusIcon(status: SectionStatus) {
  if (status === 'ready') return <CheckCircle2 size={20} className="text-success" />;
  if (status === 'blocked') return <ShieldAlert size={20} className="text-danger" />;
  return <AlertTriangle size={20} className="text-warning" />;
}

export default function PilotLaunchReadinessAdminPage() {
  usePageTitle('Pilot Launch Readiness');
  const { showToast } = useToast();

  const [report, setReport] = useState<ReadinessReport | null>(null);
  const [loading, setLoading] = useState(true);
  const [acknowledging, setAcknowledging] = useState(false);
  const [launching, setLaunching] = useState(false);
  const [launchModalOpen, setLaunchModalOpen] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<ReadinessReport>('/v2/admin/caring-community/launch-readiness');
      setReport(res.data ?? null);
    } catch {
      showToast('Failed to load launch-readiness report', 'error');
    } finally {
      setLoading(false);
    }
  }, [showToast]);

  useEffect(() => {
    load();
  }, [load]);

  const acknowledgeBoundary = async () => {
    setAcknowledging(true);
    try {
      const res = await api.post<{ acknowledged: boolean; report: ReadinessReport }>(
        '/v2/admin/caring-community/launch-readiness/acknowledge-boundary',
        {},
      );
      if (res.data?.report) setReport(res.data.report);
      showToast('Commercial boundary acknowledged', 'success');
    } catch {
      showToast('Failed to acknowledge boundary', 'error');
    } finally {
      setAcknowledging(false);
    }
  };

  const launchPilot = async () => {
    setLaunching(true);
    try {
      const res = await api.post<{
        launched_at: string;
        launched_by_id: number;
        report: ReadinessReport;
      }>('/v2/admin/caring-community/launch-readiness/launch', {});
      if (res.success) {
        if (res.data?.report) {
          setReport(res.data.report);
        } else {
          await load();
        }
        showToast('Pilot launched successfully.', 'success');
        setLaunchModalOpen(false);
      } else {
        const code = res.code ?? '';
        const msg =
          code === 'CANNOT_LAUNCH'
            ? 'Cannot launch — readiness gate is not closed.'
            : code === 'ALREADY_LAUNCHED'
            ? 'This pilot has already been launched.'
            : 'Failed to launch pilot.';
        showToast(msg, 'error');
        await load();
      }
    } catch {
      showToast('Failed to launch pilot.', 'error');
    } finally {
      setLaunching(false);
    }
  };

  const overall = report?.overall;
  const progressValue =
    overall && overall.total_section_count > 0
      ? Math.round((overall.ready_section_count / overall.total_section_count) * 100)
      : 0;
  const isLaunched = !!report?.launched;
  const canLaunch = !!report?.can_launch;
  const blockerCount = report?.blockers?.length ?? 0;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Pilot Launch Readiness"
        subtitle="AG95 — go/no-go report combining AG80–AG87 evaluation surfaces"
        icon={<Rocket size={20} />}
        actions={
          <div className="flex items-center gap-2">
            {!isLaunched && (
              <Button
                color="success"
                size="lg"
                startContent={<Rocket size={16} />}
                onPress={() => setLaunchModalOpen(true)}
                isDisabled={!canLaunch || loading}
              >
                Launch pilot
              </Button>
            )}
            <Tooltip content="Refresh">
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                onPress={load}
                isLoading={loading}
                aria-label="Refresh"
              >
                <RefreshCw size={15} />
              </Button>
            </Tooltip>
          </div>
        }
      />

      {!loading && report && (
        isLaunched ? (
          <Card className="border border-success-300 bg-success-50/50 dark:bg-success-900/10">
            <CardBody className="space-y-1 py-4">
              <div className="flex items-center gap-3">
                <CheckCircle2 size={22} className="text-success" />
                <div>
                  <p className="text-base font-semibold">Pilot launched</p>
                  <p className="text-sm text-default-600">
                    Launched on{' '}
                    {report.launched
                      ? new Date(report.launched.launched_at).toLocaleString()
                      : '—'}
                    {report.launched?.launched_by_id
                      ? ` by user #${report.launched.launched_by_id}`
                      : ''}
                    . This is a one-way milestone — readiness checks remain visible for audit.
                  </p>
                </div>
              </div>
            </CardBody>
          </Card>
        ) : canLaunch ? (
          <Card className="border border-success-300 bg-success-50/50 dark:bg-success-900/10">
            <CardBody className="flex flex-row items-center justify-between gap-3 py-4">
              <div className="flex items-center gap-3">
                <CheckCircle2 size={22} className="text-success" />
                <div>
                  <p className="text-base font-semibold">Ready to launch</p>
                  <p className="text-sm text-default-600">
                    Every readiness section is ready. Click <em>Launch pilot</em> when your
                    coordinator team is ready to make this a one-way decision.
                  </p>
                </div>
              </div>
            </CardBody>
          </Card>
        ) : (
          <Card className="border border-danger-300 bg-danger-50/50 dark:bg-danger-900/10">
            <CardBody className="space-y-1 py-4">
              <div className="flex items-center gap-3">
                <ShieldAlert size={22} className="text-danger" />
                <div>
                  <p className="text-base font-semibold">
                    Cannot launch yet — {blockerCount} blocker{blockerCount === 1 ? '' : 's'}{' '}
                    remain{blockerCount === 1 ? 's' : ''}
                  </p>
                  <p className="text-sm text-default-600">
                    Resolve every section below before the launch button is enabled.
                  </p>
                </div>
              </div>
            </CardBody>
          </Card>
        )
      )}

      {loading && (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      )}

      {!loading && report && overall && (
        <>
          <Card
            className={
              overall.status === 'ready'
                ? 'border border-success-300 bg-success-50/50 dark:bg-success-900/10'
                : overall.status === 'blocked'
                ? 'border border-danger-300 bg-danger-50/50 dark:bg-danger-900/10'
                : 'border border-warning-300 bg-warning-50/50 dark:bg-warning-900/10'
            }
          >
            <CardBody className="space-y-4 py-5">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                  {statusIcon(overall.status)}
                  <div>
                    <p className="text-lg font-semibold">
                      {overall.status === 'ready'
                        ? 'Pilot is ready to launch'
                        : overall.status === 'blocked'
                        ? 'Launch blocked — fix issues first'
                        : overall.status === 'not_started'
                        ? 'Pilot evaluation not yet run'
                        : 'Pilot needs coordinator review'}
                    </p>
                    <p className="text-sm text-default-500">{overall.summary}</p>
                  </div>
                </div>
                <Chip color={STATUS_COLORS[overall.status]} variant="flat" size="lg">
                  {overall.ready_section_count} / {overall.total_section_count} ready
                </Chip>
              </div>
              <Progress
                aria-label="Launch readiness progress"
                value={progressValue}
                color={STATUS_COLORS[overall.status]}
                className="max-w-full"
              />
              {!report.isolated_node_required && (
                <p className="text-xs text-default-500 italic">
                  Isolated-node gate is informational for hosted deployments. It only blocks launch
                  when AG85 deployment_mode is set to <code>canton_isolated_node</code>.
                </p>
              )}
            </CardBody>
          </Card>

          <div className="grid grid-cols-1 gap-4">
            {report.sections.map((section) => (
              <Card key={section.key} className="border border-[var(--color-border)]">
                <CardHeader className="flex flex-wrap items-start justify-between gap-3 pb-2">
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold">{section.label}</p>
                    <p className="text-xs text-default-500 mt-0.5">{section.summary}</p>
                  </div>
                  <div className="flex items-center gap-2 shrink-0">
                    <Chip size="sm" variant="flat" color={STATUS_COLORS[section.status]}>
                      {STATUS_LABELS[section.status]}
                    </Chip>
                    <Button
                      as={Link}
                      to={section.admin_path}
                      size="sm"
                      variant="flat"
                      endContent={<ChevronRight size={14} />}
                    >
                      Open
                    </Button>
                  </div>
                </CardHeader>
                <CardBody className="pt-0 space-y-2">
                  {section.missing.length > 0 && (
                    <div className="flex flex-wrap gap-1.5">
                      {section.missing.slice(0, 8).map((item) => (
                        <Chip
                          key={item}
                          size="sm"
                          variant="dot"
                          color={section.status === 'blocked' ? 'danger' : 'warning'}
                        >
                          {item}
                        </Chip>
                      ))}
                      {section.missing.length > 8 && (
                        <Chip size="sm" variant="flat" color="default">
                          +{section.missing.length - 8} more
                        </Chip>
                      )}
                    </div>
                  )}

                  {section.key === 'commercial_boundary' && section.status === 'needs_review' && (
                    <div>
                      <Button
                        size="sm"
                        color="primary"
                        variant="flat"
                        startContent={<ClipboardCheck size={14} />}
                        onPress={acknowledgeBoundary}
                        isLoading={acknowledging}
                      >
                        Acknowledge default matrix
                      </Button>
                    </div>
                  )}

                  {section.last_updated_at && (
                    <p className="text-[11px] text-default-400">
                      Last updated {new Date(section.last_updated_at).toLocaleString()}
                    </p>
                  )}
                </CardBody>
              </Card>
            ))}
          </div>

          <Divider />
          <p className="text-xs text-default-500">
            Report generated {new Date(report.generated_at).toLocaleString()}. Click any section to
            open the corresponding admin surface.
          </p>
        </>
      )}

      <Modal
        isOpen={launchModalOpen}
        onOpenChange={(open) => {
          if (!launching) setLaunchModalOpen(open);
        }}
        size="xl"
        backdrop="blur"
      >
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <Rocket size={20} className="text-success" />
            Launch the pilot?
          </ModalHeader>
          <ModalBody className="space-y-3">
            <p>
              Launching the pilot is a <strong>one-way action</strong>. It records the launch
              timestamp and operator on the tenant, and platform reports begin treating the
              community as live.
            </p>
            <p>
              Before continuing, confirm with your coordinator team that:
            </p>
            <ul className="list-disc pl-6 text-sm text-default-700 space-y-1">
              <li>The disclosure pack, operating policy, and commercial boundary are signed off</li>
              <li>The pre-pilot scoreboard baseline has been captured</li>
              <li>Data quality checks are green</li>
              <li>Real residents are ready to be invited</li>
            </ul>
            {report && (
              <div className="rounded-lg border border-default-200 bg-default-50 p-3 text-xs text-default-700 dark:bg-default-100/30">
                <p className="font-semibold mb-1">Section summary</p>
                <ul className="space-y-0.5">
                  {report.sections.map((s) => (
                    <li key={s.key} className="flex items-center justify-between gap-2">
                      <span>{s.label}</span>
                      <Chip size="sm" variant="flat" color={STATUS_COLORS[s.status]}>
                        {STATUS_LABELS[s.status]}
                      </Chip>
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onPress={() => setLaunchModalOpen(false)}
              isDisabled={launching}
            >
              Cancel
            </Button>
            <Button
              color="success"
              startContent={<Rocket size={16} />}
              onPress={launchPilot}
              isLoading={launching}
              isDisabled={!canLaunch}
            >
              Yes, launch the pilot
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
