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

  const overall = report?.overall;
  const progressValue =
    overall && overall.total_section_count > 0
      ? Math.round((overall.ready_section_count / overall.total_section_count) * 100)
      : 0;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Pilot Launch Readiness"
        subtitle="AG95 — go/no-go report combining AG80–AG87 evaluation surfaces"
        icon={<Rocket size={20} />}
        actions={
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
        }
      />

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
    </div>
  );
}
