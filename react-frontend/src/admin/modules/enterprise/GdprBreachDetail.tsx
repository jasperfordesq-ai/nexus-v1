// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GDPR Breach Detail
 * Detail page for viewing and managing a single data breach.
 * Route: /admin/enterprise/gdpr/breaches/:id
 */

import { useEffect, useState, useCallback, useMemo } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Card, CardBody, CardHeader, Chip, Button, Spinner,
  Textarea, Divider, Progress,
} from '@heroui/react';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import Save from 'lucide-react/icons/save';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import Shield from 'lucide-react/icons/shield';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import Search from 'lucide-react/icons/search';
import Ban from 'lucide-react/icons/ban';
import ArrowUpCircle from 'lucide-react/icons/circle-arrow-up';
import Bell from 'lucide-react/icons/bell';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader, StatusBadge } from '../../components';
import type { GdprBreachDetail as GdprBreachDetailType } from '../../api/types';

import { useTranslation } from 'react-i18next';

const severityColorMap: Record<string, 'default' | 'primary' | 'warning' | 'danger'> = {
  low: 'default',
  medium: 'primary',
  high: 'warning',
  critical: 'danger',
};

const TIMELINE_STEP_KEYS: { labelKey: string; dateField: keyof GdprBreachDetailType; icon: typeof CheckCircle }[] = [
  { labelKey: 'enterprise.gdpr_timeline_detected', dateField: 'detected_at', icon: Search },
  { labelKey: 'enterprise.gdpr_timeline_investigating', dateField: 'reported_at', icon: Search },
  { labelKey: 'enterprise.gdpr_timeline_contained', dateField: 'contained_at', icon: Shield },
  { labelKey: 'enterprise.gdpr_timeline_resolved', dateField: 'resolved_at', icon: CheckCircle },
  { labelKey: 'enterprise.gdpr_timeline_dpa_notified', dateField: 'dpa_notified_at', icon: Bell },
];

export function GdprBreachDetail() {
  const { t } = useTranslation('admin');
  const { id } = useParams();
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();

  usePageTitle(`GDPR Breach Page`);

  const [breach, setBreach] = useState<GdprBreachDetailType | null>(null);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [saveLoading, setSaveLoading] = useState(false);

  // Editable response fields
  const [rootCause, setRootCause] = useState('');
  const [remediationActions, setRemediationActions] = useState('');
  const [lessonsLearned, setLessonsLearned] = useState('');
  const [preventionMeasures, setPreventionMeasures] = useState('');

  // Live countdown timer for DPA 72-hour deadline
  const [now, setNow] = useState(Date.now());

  const breachId = useMemo(() => (id ? parseInt(id, 10) : 0), [id]);

  const loadData = useCallback(async () => {
    if (!breachId) return;
    setLoading(true);
    try {
      const res = await adminEnterprise.getGdprBreach(breachId);
      if (res.success && res.data) {
        const data = res.data as unknown as GdprBreachDetailType;
        setBreach(data);
        setRootCause(data.root_cause || '');
        setRemediationActions(data.remediation_actions || '');
        setLessonsLearned(data.lessons_learned || '');
        setPreventionMeasures(data.prevention_measures || '');
      }
    } catch {
      toast.error("GDPR Failed Load Breach");
    } finally {
      setLoading(false);
    }
  }, [breachId, toast])


  useEffect(() => {
    loadData();
  }, [loadData]);

  // Tick the countdown every minute while DPA notification is pending
  useEffect(() => {
    if (breach?.dpa_notified_at || !breach?.detected_at) return;
    const interval = setInterval(() => setNow(Date.now()), 60000);
    return () => clearInterval(interval);
  }, [breach?.dpa_notified_at, breach?.detected_at]);

  const handleStatusUpdate = async (updates: Partial<GdprBreachDetailType>) => {
    setActionLoading(true);
    try {
      const res = await adminEnterprise.updateGdprBreach(breachId, updates);
      if (res.success) {
        toast.success("GDPR Breach updated");
        loadData();
      } else {
        toast.error("GDPR Failed Update Breach");
      }
    } catch {
      toast.error("GDPR Failed Update Breach");
    } finally {
      setActionLoading(false);
    }
  };

  const handleSaveResponse = async () => {
    setSaveLoading(true);
    try {
      const res = await adminEnterprise.updateGdprBreach(breachId, {
        root_cause: rootCause.trim() || null,
        remediation_actions: remediationActions.trim() || null,
        lessons_learned: lessonsLearned.trim() || null,
        prevention_measures: preventionMeasures.trim() || null,
      });
      if (res.success) {
        toast.success("GDPR Response saved");
        loadData();
      } else {
        toast.error("GDPR Failed Save Response");
      }
    } catch {
      toast.error("GDPR Failed Save Response");
    } finally {
      setSaveLoading(false);
    }
  };

  const handleNotifyDpa = async () => {
    setActionLoading(true);
    try {
      const res = await adminEnterprise.notifyDpa(breachId);
      if (res.success) {
        toast.success("GDPR Dpa Notification sent");
        loadData();
      } else {
        toast.error("GDPR Failed Notify Dpa");
      }
    } catch {
      toast.error("GDPR Failed Notify Dpa");
    } finally {
      setActionLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="flex justify-center py-16">
        <Spinner size="lg" />
      </div>
    );
  }

  if (!breach) {
    return (
      <div className="text-center py-16">
        <p className="text-default-500">{"GDPR Breach Not Found"}</p>
        <Button
          variant="flat"
          className="mt-4"
          onPress={() => navigate(tenantPath('/admin/enterprise/gdpr/breaches'))}
        >
          {"GDPR Back to Breaches"}
        </Button>
      </div>
    );
  }

  // Compute live 72-hour countdown for DPA notification
  const detectedAt = breach.detected_at ? new Date(breach.detected_at) : null;
  const deadlineMs = detectedAt ? detectedAt.getTime() + 72 * 60 * 60 * 1000 : 0;
  const remainingMs = deadlineMs - now;
  const hoursRemaining = remainingMs / (1000 * 60 * 60);
  const isOverdue = remainingMs < 0;
  const dpaUrgent = !breach.dpa_notified_at && hoursRemaining < 24;

  const formatCountdown = (ms: number): string => {
    const abs = Math.abs(ms);
    const hours = Math.floor(abs / (1000 * 60 * 60));
    const minutes = Math.floor((abs % (1000 * 60 * 60)) / (1000 * 60));
    return `${hours}h ${minutes}m`;
  };

  return (
    <div>
      <PageHeader
        title={`GDPR Breach Detail`}
        description={`GDPR Breach Detail.`}
        actions={
          <Button
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            onPress={() => navigate(tenantPath('/admin/enterprise/gdpr/breaches'))}
            size="sm"
          >
            {"GDPR Back to Breaches"}
          </Button>
        }
      />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Main Column */}
        <div className="lg:col-span-2 space-y-6">
          {/* Overview Card */}
          <Card shadow="sm">
            <CardHeader className="px-4 pt-4 pb-0">
              <h3 className="text-lg font-semibold">{"GDPR Overview"}</h3>
            </CardHeader>
            <CardBody className="p-4 space-y-4">
              <p className="text-default-700">{breach.description}</p>

              <div className="flex flex-wrap gap-2">
                <Chip
                  size="sm"
                  variant="flat"
                  color={severityColorMap[breach.severity] || 'default'}
                  className="capitalize"
                >
                  {`GDPR Severity`}
                </Chip>
                <StatusBadge status={breach.status} />
              </div>

              {breach.data_categories_affected && breach.data_categories_affected.length > 0 && (
                <div>
                  <p className="text-sm text-default-500 mb-2">{"GDPR Data Categories Affected"}</p>
                  <div className="flex flex-wrap gap-1.5">
                    {breach.data_categories_affected.map((cat) => (
                      <Chip key={cat} size="sm" variant="bordered" className="capitalize">
                        {cat.replace(/_/g, ' ')}
                      </Chip>
                    ))}
                  </div>
                </div>
              )}
            </CardBody>
          </Card>

          {/* Impact Card */}
          <Card shadow="sm">
            <CardHeader className="px-4 pt-4 pb-0">
              <h3 className="text-lg font-semibold">{"GDPR Impact Assessment"}</h3>
            </CardHeader>
            <CardBody className="p-4">
              <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                  <p className="text-sm text-default-500">{"GDPR Records Affected"}</p>
                  <p className="text-xl font-bold">{breach.number_of_records_affected ?? 'N/A'}</p>
                </div>
                <div>
                  <p className="text-sm text-default-500">{"GDPR Users Affected"}</p>
                  <p className="text-xl font-bold">{breach.number_of_users_affected ?? 'N/A'}</p>
                </div>
                <div>
                  <p className="text-sm text-default-500">{"GDPR Detected"}</p>
                  <p className="font-medium text-sm">
                    {breach.detected_at ? new Date(breach.detected_at).toLocaleString() : 'N/A'}
                  </p>
                </div>
                <div>
                  <p className="text-sm text-default-500">{"GDPR Occurred"}</p>
                  <p className="font-medium text-sm">
                    {breach.occurred_at ? new Date(breach.occurred_at).toLocaleString() : 'N/A'}
                  </p>
                </div>
              </div>
            </CardBody>
          </Card>

          {/* Response Card */}
          <Card shadow="sm">
            <CardHeader className="px-4 pt-4 pb-0 flex justify-between items-center">
              <h3 className="text-lg font-semibold">{"GDPR Response Analysis"}</h3>
              <Button
                size="sm"
                color="primary"
                startContent={<Save size={14} />}
                onPress={handleSaveResponse}
                isLoading={saveLoading}
              >
                {"GDPR Save"}
              </Button>
            </CardHeader>
            <CardBody className="p-4 space-y-4">
              <Textarea
                label={"GDPR Root Cause"}
                placeholder={"Enter root cause..."}
                value={rootCause}
                onValueChange={setRootCause}
                variant="bordered"
                minRows={2}
              />
              <Textarea
                label={"GDPR Remediation Actions"}
                placeholder={"Enter remediation actions..."}
                value={remediationActions}
                onValueChange={setRemediationActions}
                variant="bordered"
                minRows={2}
              />
              <Textarea
                label={"GDPR Lessons Learned"}
                placeholder={"Enter lessons learned..."}
                value={lessonsLearned}
                onValueChange={setLessonsLearned}
                variant="bordered"
                minRows={2}
              />
              <Textarea
                label={"GDPR Prevention Measures"}
                placeholder={"Enter prevention measures..."}
                value={preventionMeasures}
                onValueChange={setPreventionMeasures}
                variant="bordered"
                minRows={2}
              />
            </CardBody>
          </Card>
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Timeline Card */}
          <Card shadow="sm">
            <CardHeader className="px-4 pt-4 pb-0">
              <h3 className="text-lg font-semibold">{"GDPR Timeline"}</h3>
            </CardHeader>
            <CardBody className="p-4">
              <div className="relative pl-6">
                <div className="absolute left-2 top-1 bottom-1 w-0.5 bg-default-200" />

                <div className="space-y-5">
                  {TIMELINE_STEP_KEYS.map((step) => {
                    const dateValue = breach[step.dateField] as string | null;
                    const completed = !!dateValue;
                    const Icon = step.icon;

                    return (
                      <div key={step.dateField} className="relative">
                        <div className={`
                          absolute -left-4 top-0.5 h-3 w-3 rounded-full border-2
                          ${completed
                            ? 'border-success bg-success'
                            : 'border-default-300 bg-background'
                          }
                        `} />
                        <div className="flex items-center gap-2">
                          <Icon size={14} className={completed ? 'text-success' : 'text-default-300'} />
                          <span className={`text-sm font-medium ${completed ? 'text-foreground' : 'text-default-400'}`}>
                            {t(step.labelKey)}
                          </span>
                        </div>
                        {completed && (
                          <p className="text-xs text-default-400 mt-0.5 ml-6">
                            {new Date(dateValue!).toLocaleString()}
                          </p>
                        )}
                      </div>
                    );
                  })}
                </div>
              </div>
            </CardBody>
          </Card>

          {/* DPA Notification Card */}
          <Card shadow="sm">
            <CardHeader className="px-4 pt-4 pb-0">
              <h3 className="text-lg font-semibold flex items-center gap-2">
                <Bell size={18} />
                {"GDPR Dpa Notification"}
              </h3>
            </CardHeader>
            <CardBody className="p-4 space-y-3">
              {breach.dpa_notified_at ? (
                <>
                  <div className="flex items-center gap-2 text-success">
                    <CheckCircle size={16} />
                    <span className="text-sm font-medium">{"GDPR Notified"}</span>
                  </div>
                  <div>
                    <p className="text-sm text-default-500">{"GDPR Date"}</p>
                    <p className="font-medium text-sm">
                      {new Date(breach.dpa_notified_at).toLocaleString()}
                    </p>
                  </div>
                  {breach.authority_reference && (
                    <div>
                      <p className="text-sm text-default-500">{"GDPR Reference"}</p>
                      <p className="font-medium text-sm">{breach.authority_reference}</p>
                    </div>
                  )}
                </>
              ) : (
                <>
                  {detectedAt && (
                    <Card shadow="none" className={`border-2 ${isOverdue ? 'border-danger' : hoursRemaining < 24 ? 'border-warning' : 'border-success'}`}>
                      <CardBody className="p-4 text-center">
                        <p className="text-xs text-default-500 mb-1">{"GDPR Dpa Deadline"}</p>
                        <p className={`text-2xl font-bold ${isOverdue ? 'text-danger' : hoursRemaining < 24 ? 'text-warning' : 'text-success'}`}>
                          {isOverdue ? '\u2212' : ''}{formatCountdown(remainingMs)}
                        </p>
                        <p className={`text-xs mt-1 ${isOverdue ? 'text-danger' : 'text-default-400'}`}>
                          {isOverdue ? "GDPR Dpa Overdue" : hoursRemaining < 24 ? "GDPR Dpa Less Than 24h" : "GDPR Dpa Remaining"}
                        </p>
                        <Progress
                          value={isOverdue ? 100 : Math.min(100, ((72 - hoursRemaining) / 72) * 100)}
                          color={isOverdue ? 'danger' : hoursRemaining < 24 ? 'warning' : 'success'}
                          size="sm"
                          className="mt-3"
                        />
                      </CardBody>
                    </Card>
                  )}
                  {dpaUrgent && (
                    <div className="p-3 rounded-lg bg-danger-50 border border-danger-200">
                      <div className="flex items-center gap-2 text-danger">
                        <AlertTriangle size={16} />
                        <span className="text-sm font-semibold">
                          {isOverdue ? "GDPR Deadline Exceeded" : "GDPR Deadline Approaching"}
                        </span>
                      </div>
                      <p className="text-xs text-danger-600 mt-1">
                        {"GDPR Dpa 72h Requirement"}
                      </p>
                    </div>
                  )}
                  <Button
                    color="warning"
                    variant="flat"
                    startContent={<Bell size={14} />}
                    onPress={handleNotifyDpa}
                    isLoading={actionLoading}
                    className="w-full"
                    size="sm"
                  >
                    {"GDPR Notify Dpa"}
                  </Button>
                </>
              )}
            </CardBody>
          </Card>

          {/* Actions Card */}
          <Card shadow="sm">
            <CardHeader className="px-4 pt-4 pb-0">
              <h3 className="text-lg font-semibold">{"GDPR Actions"}</h3>
            </CardHeader>
            <CardBody className="p-4 space-y-2">
              {breach.status === 'open' && (
                <Button
                  color="primary"
                  variant="flat"
                  startContent={<Search size={14} />}
                  onPress={() => handleStatusUpdate({ status: 'investigating' })}
                  isLoading={actionLoading}
                  className="w-full"
                  size="sm"
                >
                  {"GDPR Mark Investigating"}
                </Button>
              )}
              {(breach.status === 'open' || breach.status === 'investigating') && (
                <Button
                  color="warning"
                  variant="flat"
                  startContent={<Ban size={14} />}
                  onPress={() => handleStatusUpdate({ contained_at: new Date().toISOString(), status: 'contained' })}
                  isLoading={actionLoading}
                  className="w-full"
                  size="sm"
                >
                  {"GDPR Mark Contained"}
                </Button>
              )}
              {breach.status !== 'resolved' && (
                <Button
                  color="success"
                  variant="flat"
                  startContent={<CheckCircle size={14} />}
                  onPress={() => handleStatusUpdate({ resolved_at: new Date().toISOString(), status: 'resolved' })}
                  isLoading={actionLoading}
                  className="w-full"
                  size="sm"
                >
                  {"GDPR Mark Resolved"}
                </Button>
              )}
              <Divider />
              <Button
                color="danger"
                variant="flat"
                startContent={<ArrowUpCircle size={14} />}
                onPress={() => handleStatusUpdate({ escalated_at: new Date().toISOString() })}
                isLoading={actionLoading}
                className="w-full"
                size="sm"
              >
                {"GDPR Escalate"}
              </Button>
            </CardBody>
          </Card>
        </div>
      </div>
    </div>
  );
}

export default GdprBreachDetail;
