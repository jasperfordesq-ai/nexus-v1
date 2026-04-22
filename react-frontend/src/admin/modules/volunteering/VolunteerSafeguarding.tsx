// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Volunteer Safeguarding
 * Admin page for managing safeguarding incidents and DLP assignments.
 */

import { useState, useCallback, useEffect } from 'react';
import {
  Button,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Select,
  SelectItem,
  Textarea,
  Input,
  Card,
  CardBody,
  CardHeader,
} from '@heroui/react';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import Search from 'lucide-react/icons/search';
import Eye from 'lucide-react/icons/eye';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import Clock from 'lucide-react/icons/clock';
import Users from 'lucide-react/icons/users';
import Activity from 'lucide-react/icons/activity';
import ArrowRight from 'lucide-react/icons/arrow-right';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminVolunteering } from '../../api/adminApi';
import { DataTable, PageHeader, StatCard, EmptyState, type Column } from '../../components';
import { useTranslation } from 'react-i18next';

// ── Types ──────────────────────────────────────────────────────────────────────

interface Incident {
  id: number;
  type: 'concern' | 'allegation' | 'disclosure' | 'near_miss' | 'other';
  severity: 'low' | 'medium' | 'high' | 'critical';
  reporter_name: string;
  subject_name: string;
  organization_name: string;
  status: 'open' | 'investigating' | 'resolved' | 'escalated' | 'closed';
  date: string;
  description?: string;
  action_taken?: string;
  resolution_notes?: string;
}

interface IncidentStats {
  total_incidents: number;
  open: number;
  under_investigation: number;
  resolved: number;
}

interface DlpAssignment {
  organization_id: number;
  organization_name: string;
  dlp_user_id: number | null;
  dlp_user_name: string | null;
}

// ── Helpers ────────────────────────────────────────────────────────────────────

const STATUS_COLORS: Record<string, 'warning' | 'success' | 'danger' | 'primary' | 'default'> = {
  open: 'warning',
  investigating: 'primary',
  resolved: 'success',
  escalated: 'danger',
  closed: 'default',
};

const SEVERITY_COLORS: Record<string, 'success' | 'warning' | 'danger'> = {
  low: 'success',
  medium: 'warning',
  high: 'danger',
  critical: 'danger',
};

const TYPE_LABELS: Record<string, string> = {
  concern: 'Concern',
  allegation: 'Allegation',
  disclosure: 'Disclosure',
  near_miss: 'Near Miss',
  other: 'Other',
};

function parsePayload<T>(raw: unknown): T {
  if (raw && typeof raw === 'object' && 'data' in raw) {
    return (raw as { data: T }).data;
  }
  return raw as T;
}

// ── Component ──────────────────────────────────────────────────────────────────

export function VolunteerSafeguarding() {
  const { t } = useTranslation('admin');
  usePageTitle(t('volunteering.safeguarding_page_title', 'Safeguarding & Incidents'));
  const toast = useToast();

  const [incidents, setIncidents] = useState<Incident[]>([]);
  const [stats, setStats] = useState<IncidentStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);

  // Update modal
  const [updateModal, setUpdateModal] = useState(false);
  const [selectedIncident, setSelectedIncident] = useState<Incident | null>(null);
  const [updateStatus, setUpdateStatus] = useState<string>('open');
  const [actionTaken, setActionTaken] = useState('');
  const [resolutionNotes, setResolutionNotes] = useState('');

  // DLP assignments
  const [dlpAssignments, setDlpAssignments] = useState<DlpAssignment[]>([]);
  const [dlpLoading, setDlpLoading] = useState(false);
  const [dlpModal, setDlpModal] = useState(false);
  const [selectedOrg, setSelectedOrg] = useState<DlpAssignment | null>(null);
  const [dlpUserId, setDlpUserId] = useState('');

  // ── Data loading ───────────────────────────────────────────────────────────

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminVolunteering.getIncidents();
      if (res.success && res.data) {
        const payload = parsePayload<{
          incidents?: Incident[];
          stats?: IncidentStats;
          dlp_assignments?: DlpAssignment[];
        }>(res.data);
        setIncidents(payload.incidents || []);
        setStats(payload.stats || null);
        setDlpAssignments(payload.dlp_assignments || []);
      }
    } catch {
      toast.error(t('volunteering.failed_to_load_incidents', 'Failed to load incidents'));
      setIncidents([]);
      setStats(null);
    }
    setLoading(false);
  }, [toast]);


  useEffect(() => { loadData(); }, [loadData]);

  // ── Actions ────────────────────────────────────────────────────────────────

  const openUpdate = (incident: Incident) => {
    setSelectedIncident(incident);
    setUpdateStatus(incident.status);
    setActionTaken(incident.action_taken || '');
    setResolutionNotes(incident.resolution_notes || '');
    setUpdateModal(true);
  };

  const handleUpdate = async () => {
    if (!selectedIncident) return;
    setActionLoading(true);
    try {
      const data: { status: string; action_taken?: string; resolution_notes?: string } = {
        status: updateStatus,
      };
      if (actionTaken.trim()) data.action_taken = actionTaken.trim();
      if (resolutionNotes.trim()) data.resolution_notes = resolutionNotes.trim();

      const res = await adminVolunteering.updateIncident(selectedIncident.id, data);
      if (res.success) {
        toast.success(t('volunteering.incident_updated', 'Incident updated successfully'));
        setUpdateModal(false);
        loadData();
      } else {
        toast.error(t('volunteering.failed_to_update_incident', 'Failed to update incident'));
      }
    } catch {
      toast.error(t('volunteering.failed_to_update_incident', 'Failed to update incident'));
    }
    setActionLoading(false);
  };

  const openDlpAssign = (assignment: DlpAssignment) => {
    setSelectedOrg(assignment);
    setDlpUserId(assignment.dlp_user_id ? String(assignment.dlp_user_id) : '');
    setDlpModal(true);
  };

  const handleDlpAssign = async () => {
    if (!selectedOrg) return;
    const userId = parseInt(dlpUserId, 10);
    if (isNaN(userId) || userId <= 0) {
      toast.error(t('volunteering.invalid_user_id', 'Please enter a valid user ID'));
      return;
    }
    setDlpLoading(true);
    try {
      const res = await adminVolunteering.assignDlp(selectedOrg.organization_id, userId);
      if (res.success) {
        toast.success(t('volunteering.dlp_assigned', 'DLP assigned successfully'));
        setDlpModal(false);
        loadData();
      } else {
        toast.error(t('volunteering.failed_to_assign_dlp', 'Failed to assign DLP'));
      }
    } catch {
      toast.error(t('volunteering.failed_to_assign_dlp', 'Failed to assign DLP'));
    }
    setDlpLoading(false);
  };

  // ── Columns ────────────────────────────────────────────────────────────────

  const columns: Column<Incident>[] = [
    {
      key: 'type',
      label: t('volunteering.col_incident_type', 'Type'),
      sortable: true,
      render: (item) => (
        <Chip size="sm" variant="flat">
          {TYPE_LABELS[item.type] || item.type}
        </Chip>
      ),
    },
    {
      key: 'severity',
      label: t('volunteering.col_severity', 'Severity'),
      sortable: true,
      render: (item) => (
        <Chip
          size="sm"
          color={SEVERITY_COLORS[item.severity] || 'default'}
          variant="flat"
          className={item.severity === 'critical' ? 'font-bold' : ''}
        >
          {item.severity.charAt(0).toUpperCase() + item.severity.slice(1)}
        </Chip>
      ),
    },
    {
      key: 'reporter_name',
      label: t('volunteering.col_reporter', 'Reporter'),
      sortable: true,
    },
    {
      key: 'subject_name',
      label: t('volunteering.col_subject', 'Subject'),
      sortable: true,
    },
    {
      key: 'organization_name',
      label: t('volunteering.col_organization', 'Organization'),
      sortable: true,
    },
    {
      key: 'status',
      label: t('volunteering.col_status', 'Status'),
      sortable: true,
      render: (item) => (
        <Chip size="sm" color={STATUS_COLORS[item.status] || 'default'} variant="flat">
          {item.status === 'investigating'
            ? 'Under Investigation'
            : item.status.charAt(0).toUpperCase() + item.status.slice(1)}
        </Chip>
      ),
    },
    {
      key: 'date',
      label: t('volunteering.col_date', 'Date'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.date ? new Date(item.date).toLocaleDateString() : '--'}
        </span>
      ),
    },
    {
      key: 'actions',
      label: t('volunteering.col_actions', 'Actions'),
      render: (item) => (
        <Button
          size="sm"
          variant="flat"
          color="primary"
          startContent={<Eye size={14} />}
          onPress={() => openUpdate(item)}
        >
          {t('volunteering.update_status', 'Update')}
        </Button>
      ),
    },
  ];

  // ── Render ─────────────────────────────────────────────────────────────────

  return (
    <div>
      <PageHeader
        title={t('volunteering.safeguarding_title', 'Safeguarding & Incidents')}
        description={t('volunteering.safeguarding_desc', 'Track and manage safeguarding incidents and concerns')}
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadData}
            isLoading={loading}
          >
            {t('common.refresh', 'Refresh')}
          </Button>
        }
      />

      {/* Stats Row */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label={t('volunteering.stat_total_incidents', 'Total Incidents')}
          value={stats?.total_incidents ?? 0}
          icon={ShieldAlert}
          color="default"
          loading={loading}
        />
        <StatCard
          label={t('volunteering.stat_open', 'Open')}
          value={stats?.open ?? 0}
          icon={AlertTriangle}
          color="warning"
          loading={loading}
        />
        <StatCard
          label={t('volunteering.stat_under_investigation', 'Under Investigation')}
          value={stats?.under_investigation ?? 0}
          icon={Search}
          color="primary"
          loading={loading}
        />
        <StatCard
          label={t('volunteering.stat_resolved', 'Resolved')}
          value={stats?.resolved ?? 0}
          icon={CheckCircle}
          color="success"
          loading={loading}
        />
      </div>

      {/* Incidents Table */}
      {!loading && incidents.length === 0 ? (
        <EmptyState
          icon={ShieldAlert}
          title={t('volunteering.no_incidents', 'No incidents reported')}
          description={t('volunteering.no_incidents_desc', 'There are no safeguarding incidents to review.')}
        />
      ) : (
        <DataTable columns={columns} data={incidents} isLoading={loading} onRefresh={loadData} />
      )}

      {/* DLP Assignments Section */}
      <Card className="mt-6">
        <CardHeader>
          <div className="flex items-center gap-2">
            <Users size={18} />
            <span className="font-semibold">
              {t('volunteering.dlp_assignments_title', 'Designated Liaison Persons (DLP)')}
            </span>
          </div>
        </CardHeader>
        <CardBody>
          {dlpAssignments.length === 0 ? (
            <p className="text-default-400 text-sm">
              {t('volunteering.no_dlp_assignments', 'No organizations loaded. DLP assignments will appear here once incidents data is loaded.')}
            </p>
          ) : (
            <div className="space-y-3">
              {dlpAssignments.map((assignment) => (
                <div
                  key={assignment.organization_id}
                  className="flex items-center justify-between p-3 rounded-lg bg-default-50"
                >
                  <div>
                    <p className="font-medium">{assignment.organization_name}</p>
                    <p className="text-sm text-default-400">
                      {t('volunteering.dlp_label', 'DLP')}:{' '}
                      {assignment.dlp_user_name ? (
                        <span className="text-success font-medium">{assignment.dlp_user_name}</span>
                      ) : (
                        <span className="text-warning">{t('volunteering.not_assigned', 'Not assigned')}</span>
                      )}
                    </p>
                  </div>
                  <Button
                    size="sm"
                    variant="flat"
                    color={assignment.dlp_user_id ? 'default' : 'warning'}
                    onPress={() => openDlpAssign(assignment)}
                  >
                    {assignment.dlp_user_id
                      ? t('volunteering.change_dlp', 'Change DLP')
                      : t('volunteering.assign_dlp', 'Assign DLP')}
                  </Button>
                </div>
              ))}
            </div>
          )}
        </CardBody>
      </Card>

      {/* Recent Actions / Audit Log */}
      {incidents.length > 0 && (
        <Card className="mt-6">
          <CardHeader>
            <div className="flex items-center gap-2">
              <Activity size={18} />
              <span className="font-semibold">
                {t('volunteering.recent_actions_title', 'Recent Actions')}
              </span>
            </div>
          </CardHeader>
          <CardBody>
            <div className="relative">
              {/* Timeline line */}
              <div className="absolute left-[15px] top-2 bottom-2 w-0.5 bg-default-200" />

              <div className="space-y-4">
                {[...incidents]
                  .sort((a, b) => {
                    const dateA = new Date(a.date || 0).getTime();
                    const dateB = new Date(b.date || 0).getTime();
                    return dateB - dateA;
                  })
                  .slice(0, 20)
                  .map((incident) => (
                    <div key={incident.id} className="flex items-start gap-3 relative pl-9">
                      {/* Timeline dot */}
                      <div
                        className={`absolute left-[10px] top-1.5 w-3 h-3 rounded-full border-2 border-background ${
                          incident.status === 'resolved' || incident.status === 'closed'
                            ? 'bg-success'
                            : incident.status === 'escalated'
                              ? 'bg-danger'
                              : incident.status === 'investigating'
                                ? 'bg-primary'
                                : 'bg-warning'
                        }`}
                      />
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 flex-wrap">
                          <span className="text-sm font-medium">
                            {TYPE_LABELS[incident.type] || incident.type}
                          </span>
                          <ArrowRight size={12} className="text-default-400" />
                          <Chip
                            size="sm"
                            color={STATUS_COLORS[incident.status] || 'default'}
                            variant="flat"
                          >
                            {incident.status === 'investigating'
                              ? 'Under Investigation'
                              : incident.status.charAt(0).toUpperCase() + incident.status.slice(1)}
                          </Chip>
                          <Chip
                            size="sm"
                            color={SEVERITY_COLORS[incident.severity] || 'default'}
                            variant="dot"
                          >
                            {incident.severity}
                          </Chip>
                        </div>
                        <p className="text-xs text-default-500 mt-0.5">
                          {incident.subject_name}
                          {incident.organization_name && ` — ${incident.organization_name}`}
                          {' | '}
                          {t('volunteering.reported_by', 'Reported by {{name}}', { name: incident.reporter_name })}
                        </p>
                        {incident.action_taken && (
                          <p className="text-xs text-default-400 mt-0.5 italic">
                            {incident.action_taken}
                          </p>
                        )}
                        <p className="text-xs text-default-400 mt-0.5">
                          {incident.date ? new Date(incident.date).toLocaleString() : '--'}
                        </p>
                      </div>
                    </div>
                  ))}
              </div>
            </div>
          </CardBody>
        </Card>
      )}

      {/* Update Incident Modal */}
      <Modal isOpen={updateModal} onClose={() => setUpdateModal(false)} size="lg">
        <ModalContent>
          <ModalHeader>
            {t('volunteering.update_incident', 'Update Incident')}
          </ModalHeader>
          <ModalBody>
            {selectedIncident && (
              <div className="space-y-4">
                <div className="grid grid-cols-2 gap-3 text-sm">
                  <div>
                    <span className="text-default-400">{t('volunteering.col_incident_type', 'Type')}:</span>
                    <p className="font-medium">{TYPE_LABELS[selectedIncident.type] || selectedIncident.type}</p>
                  </div>
                  <div>
                    <span className="text-default-400">{t('volunteering.col_severity', 'Severity')}:</span>
                    <p>
                      <Chip
                        size="sm"
                        color={SEVERITY_COLORS[selectedIncident.severity] || 'default'}
                        variant="flat"
                        className={selectedIncident.severity === 'critical' ? 'font-bold' : ''}
                      >
                        {selectedIncident.severity.charAt(0).toUpperCase() + selectedIncident.severity.slice(1)}
                      </Chip>
                    </p>
                  </div>
                  <div>
                    <span className="text-default-400">{t('volunteering.col_reporter', 'Reporter')}:</span>
                    <p className="font-medium">{selectedIncident.reporter_name}</p>
                  </div>
                  <div>
                    <span className="text-default-400">{t('volunteering.col_subject', 'Subject')}:</span>
                    <p className="font-medium">{selectedIncident.subject_name}</p>
                  </div>
                </div>

                {selectedIncident.description && (
                  <div>
                    <span className="text-default-400 text-sm">{t('volunteering.description', 'Description')}:</span>
                    <p className="text-sm mt-1">{selectedIncident.description}</p>
                  </div>
                )}

                <Select
                  label={t('volunteering.status', 'Status')}
                  selectedKeys={[updateStatus]}
                  onSelectionChange={(keys) => setUpdateStatus(Array.from(keys)[0] as string)}
                >
                  <SelectItem key="open">{t('volunteering.status_open', 'Open')}</SelectItem>
                  <SelectItem key="investigating">{t('volunteering.status_investigating', 'Under Investigation')}</SelectItem>
                  <SelectItem key="resolved">{t('volunteering.status_resolved', 'Resolved')}</SelectItem>
                  <SelectItem key="escalated">{t('volunteering.status_escalated', 'Escalated')}</SelectItem>
                  <SelectItem key="closed">{t('volunteering.status_closed', 'Closed')}</SelectItem>
                </Select>

                <Textarea
                  label={t('volunteering.action_taken', 'Action Taken')}
                  placeholder={t('volunteering.action_taken_placeholder', 'Describe what actions have been taken...')}
                  value={actionTaken}
                  onValueChange={setActionTaken}
                />

                <Textarea
                  label={t('volunteering.resolution_notes', 'Resolution Notes')}
                  placeholder={t('volunteering.resolution_notes_placeholder', 'Notes about the resolution or current status...')}
                  value={resolutionNotes}
                  onValueChange={setResolutionNotes}
                />
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setUpdateModal(false)}>
              {t('common.cancel', 'Cancel')}
            </Button>
            <Button
              color="primary"
              onPress={handleUpdate}
              isLoading={actionLoading}
              startContent={<Clock size={16} />}
            >
              {t('volunteering.update_incident', 'Update Incident')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* DLP Assignment Modal */}
      <Modal isOpen={dlpModal} onClose={() => setDlpModal(false)} size="md">
        <ModalContent>
          <ModalHeader>
            {t('volunteering.assign_dlp_title', 'Assign Designated Liaison Person')}
            {selectedOrg && ` — ${selectedOrg.organization_name}`}
          </ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <p className="text-sm text-default-500">
                {t('volunteering.dlp_explanation', 'The Designated Liaison Person (DLP) is responsible for receiving and handling safeguarding concerns for this organization.')}
              </p>
              <Input
                label={t('volunteering.dlp_user_id', 'User ID')}
                type="number"
                placeholder={t('volunteering.dlp_user_id_placeholder', 'Enter the user ID of the DLP')}
                value={dlpUserId}
                onValueChange={setDlpUserId}
              />
              {selectedOrg?.dlp_user_name && (
                <p className="text-sm text-default-400">
                  {t('volunteering.current_dlp', 'Current DLP')}: <span className="font-medium text-default-600">{selectedOrg.dlp_user_name}</span>
                </p>
              )}
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setDlpModal(false)}>
              {t('common.cancel', 'Cancel')}
            </Button>
            <Button
              color="primary"
              onPress={handleDlpAssign}
              isLoading={dlpLoading}
            >
              {t('volunteering.assign', 'Assign')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default VolunteerSafeguarding;
