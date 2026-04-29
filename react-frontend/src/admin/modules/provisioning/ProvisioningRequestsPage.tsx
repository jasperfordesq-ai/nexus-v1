// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AG44 — Super-admin tenant provisioning queue.
 *
 * GET  /v2/super-admin/provisioning-requests
 * GET  /v2/super-admin/provisioning-requests/{id}
 * POST /v2/super-admin/provisioning-requests/{id}/approve
 * POST /v2/super-admin/provisioning-requests/{id}/reject
 * POST /v2/super-admin/provisioning-requests/{id}/retry
 *
 * ENGLISH-ONLY (admin panel policy).
 */

import { useCallback, useEffect, useState } from 'react';
import {
  Button,
  Chip,
  Spinner,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Textarea,
  useDisclosure,
  Card,
  CardBody,
} from '@heroui/react';
import Building from 'lucide-react/icons/building';
import CheckCircle2 from 'lucide-react/icons/check-circle-2';
import XCircle from 'lucide-react/icons/x-circle';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Globe from 'lucide-react/icons/globe';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { PageHeader } from '../../components';

interface ProvisioningRequest {
  id: number;
  applicant_name: string;
  applicant_email: string;
  applicant_phone: string | null;
  org_name: string;
  country_code: string;
  region_or_canton: string | null;
  requested_slug: string;
  requested_subdomain: string | null;
  tenant_category: string;
  languages: string | null;
  default_language: string;
  expected_member_count_bucket: string | null;
  intended_use: string | null;
  status: string;
  reviewed_by: number | null;
  reviewed_at: string | null;
  rejection_reason: string | null;
  provisioned_tenant_id: number | null;
  provisioning_log: string | null;
  created_at: string;
  updated_at: string;
}

const STATUS_FILTERS = [
  { key: '',             label: 'All',           color: 'default' as const },
  { key: 'pending',      label: 'Pending',       color: 'default' as const },
  { key: 'under_review', label: 'Under Review',  color: 'primary' as const },
  { key: 'approved',     label: 'Approved',      color: 'primary' as const },
  { key: 'provisioned',  label: 'Provisioned',   color: 'success' as const },
  { key: 'rejected',     label: 'Rejected',      color: 'danger' as const },
  { key: 'failed',       label: 'Failed',        color: 'warning' as const },
];

function statusColor(status: string): 'default' | 'primary' | 'success' | 'warning' | 'danger' {
  return STATUS_FILTERS.find(s => s.key === status)?.color ?? 'default';
}

export function ProvisioningRequestsPage() {
  usePageTitle('Provisioning Queue');
  const toast = useToast();

  const [requests, setRequests] = useState<ProvisioningRequest[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('');
  const [selected, setSelected] = useState<ProvisioningRequest | null>(null);
  const [rejectionReason, setRejectionReason] = useState('');
  const [actionInFlight, setActionInFlight] = useState(false);
  const detail = useDisclosure();

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get('/v2/super-admin/provisioning-requests' + (filter ? `?status=${filter}` : ''));
      const data = (res && typeof res === 'object' && 'data' in res ? (res as { data: unknown }).data : res) as
        | ProvisioningRequest[]
        | undefined;
      setRequests(Array.isArray(data) ? data : []);
    } catch (err) {
      logError('ProvisioningRequestsPage load failed', err);
      toast.error('Failed to load provisioning requests');
    } finally {
      setLoading(false);
    }
  }, [filter, toast]);

  useEffect(() => { load(); }, [load]);

  function openDetail(req: ProvisioningRequest) {
    setSelected(req);
    setRejectionReason(req.rejection_reason ?? '');
    detail.onOpen();
  }

  async function handleApprove() {
    if (!selected) return;
    setActionInFlight(true);
    try {
      await api.post(`/v2/super-admin/provisioning-requests/${selected.id}/approve`, {});
      toast.success('Approval queued — provisioning in progress');
      detail.onClose();
      await load();
    } catch (err) {
      logError('approve failed', err);
      toast.error('Failed to approve');
    } finally {
      setActionInFlight(false);
    }
  }

  async function handleReject() {
    if (!selected) return;
    if (!rejectionReason.trim()) {
      toast.error('Please enter a rejection reason');
      return;
    }
    setActionInFlight(true);
    try {
      await api.post(`/v2/super-admin/provisioning-requests/${selected.id}/reject`, {
        reason: rejectionReason.trim(),
      });
      toast.success('Request rejected — applicant notified by email');
      detail.onClose();
      await load();
    } catch (err) {
      logError('reject failed', err);
      toast.error('Failed to reject');
    } finally {
      setActionInFlight(false);
    }
  }

  async function handleRetry() {
    if (!selected) return;
    setActionInFlight(true);
    try {
      await api.post(`/v2/super-admin/provisioning-requests/${selected.id}/retry`, {});
      toast.success('Retry queued');
      detail.onClose();
      await load();
    } catch (err) {
      logError('retry failed', err);
      toast.error('Failed to retry');
    } finally {
      setActionInFlight(false);
    }
  }

  let parsedLog: unknown = null;
  let parsedLanguages: string[] = [];
  if (selected) {
    try { parsedLog = selected.provisioning_log ? JSON.parse(selected.provisioning_log) : null; } catch { /* ignore */ }
    try { parsedLanguages = selected.languages ? JSON.parse(selected.languages) : []; } catch { /* ignore */ }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Provisioning Queue"
        subtitle="Review and approve self-service tenant provisioning requests"
        icon={<Building className="w-6 h-6" />}
        actions={
          <Button
            size="sm"
            variant="flat"
            startContent={<RefreshCw className="w-4 h-4" />}
            onPress={load}
            isLoading={loading}
          >
            Refresh
          </Button>
        }
      />

      {/* Status filter chips */}
      <div className="flex items-center gap-2 flex-wrap">
        {STATUS_FILTERS.map(s => (
          <Button
            key={s.key || 'all'}
            size="sm"
            variant={filter === s.key ? 'solid' : 'flat'}
            color={filter === s.key ? s.color : 'default'}
            onPress={() => setFilter(s.key)}
          >
            {s.label}
          </Button>
        ))}
      </div>

      {/* Grid */}
      {loading ? (
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      ) : requests.length === 0 ? (
        <div className="text-center py-16 text-gray-400">
          <Building className="w-12 h-12 mx-auto mb-3 opacity-30" />
          <p>No provisioning requests in this status.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {requests.map(req => (
            <Card
              key={req.id}
              isPressable
              onPress={() => openDetail(req)}
              className="cursor-pointer hover:shadow-md transition-shadow"
            >
              <CardBody className="p-4 space-y-2">
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0">
                    <p className="font-semibold text-sm leading-tight truncate">{req.org_name}</p>
                    <p className="text-xs text-gray-500 truncate">
                      {req.country_code}{req.region_or_canton ? ` · ${req.region_or_canton}` : ''}
                    </p>
                  </div>
                  <Chip size="sm" color={statusColor(req.status)} variant="flat">{req.status}</Chip>
                </div>
                <p className="text-xs text-gray-500 truncate">
                  <Globe className="inline w-3 h-3 mr-1" />/{req.requested_slug}
                </p>
                <p className="text-xs text-gray-400 truncate">{req.applicant_name} — {req.applicant_email}</p>
                <p className="text-xs text-gray-400">
                  {new Date(req.created_at).toLocaleString()}
                </p>
              </CardBody>
            </Card>
          ))}
        </div>
      )}

      {/* Detail modal */}
      <Modal isOpen={detail.isOpen} onClose={detail.onClose} size="3xl" scrollBehavior="inside">
        <ModalContent>
          {selected && (
            <>
              <ModalHeader className="flex items-center gap-2">
                <Building className="w-5 h-5 text-indigo-500" />
                {selected.org_name}
                <Chip size="sm" color={statusColor(selected.status)} variant="flat">
                  {selected.status}
                </Chip>
              </ModalHeader>
              <ModalBody className="space-y-4 text-sm">
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <p className="text-gray-400 text-xs uppercase tracking-wide mb-0.5">Applicant</p>
                    <p>{selected.applicant_name}</p>
                    <p className="text-indigo-400">{selected.applicant_email}</p>
                    {selected.applicant_phone && <p>{selected.applicant_phone}</p>}
                  </div>
                  <div>
                    <p className="text-gray-400 text-xs uppercase tracking-wide mb-0.5">Country / Region</p>
                    <p>{selected.country_code}{selected.region_or_canton ? ` · ${selected.region_or_canton}` : ''}</p>
                  </div>
                  <div>
                    <p className="text-gray-400 text-xs uppercase tracking-wide mb-0.5">Slug</p>
                    <p className="font-mono">{selected.requested_slug}</p>
                  </div>
                  <div>
                    <p className="text-gray-400 text-xs uppercase tracking-wide mb-0.5">Subdomain</p>
                    <p className="font-mono">{selected.requested_subdomain ?? '—'}</p>
                  </div>
                  <div>
                    <p className="text-gray-400 text-xs uppercase tracking-wide mb-0.5">Category</p>
                    <p>{selected.tenant_category}</p>
                  </div>
                  <div>
                    <p className="text-gray-400 text-xs uppercase tracking-wide mb-0.5">Expected size</p>
                    <p>{selected.expected_member_count_bucket ?? '—'}</p>
                  </div>
                  <div>
                    <p className="text-gray-400 text-xs uppercase tracking-wide mb-0.5">Languages</p>
                    <p>{parsedLanguages.length > 0 ? parsedLanguages.join(', ') : '—'} (default: {selected.default_language})</p>
                  </div>
                  <div>
                    <p className="text-gray-400 text-xs uppercase tracking-wide mb-0.5">Provisioned tenant</p>
                    <p>{selected.provisioned_tenant_id ? `#${selected.provisioned_tenant_id}` : '—'}</p>
                  </div>
                </div>

                {selected.intended_use && (
                  <div>
                    <p className="text-gray-400 text-xs uppercase tracking-wide mb-1">Intended use</p>
                    <p className="text-gray-300 italic whitespace-pre-wrap">{selected.intended_use}</p>
                  </div>
                )}

                {selected.rejection_reason && (
                  <div>
                    <p className="text-gray-400 text-xs uppercase tracking-wide mb-1">Rejection reason</p>
                    <p className="text-rose-400">{selected.rejection_reason}</p>
                  </div>
                )}

                {parsedLog !== null && (
                  <div>
                    <p className="text-gray-400 text-xs uppercase tracking-wide mb-1">Provisioning log</p>
                    <pre className="text-xs bg-gray-800/40 rounded p-2 overflow-x-auto">
                      {JSON.stringify(parsedLog, null, 2)}
                    </pre>
                  </div>
                )}

                {/* Reject reason input (only relevant for pending/under_review) */}
                {(selected.status === 'pending' || selected.status === 'under_review') && (
                  <div className="border-t border-white/10 pt-4">
                    <p className="text-gray-400 text-xs uppercase tracking-wide mb-2">
                      Rejection reason (required if rejecting)
                    </p>
                    <Textarea
                      size="sm"
                      minRows={2}
                      value={rejectionReason}
                      onValueChange={setRejectionReason}
                      placeholder="Why is this being rejected?"
                    />
                  </div>
                )}

                <div className="border-t border-white/10 pt-3 grid grid-cols-2 gap-2 text-xs text-gray-400">
                  <div><span className="font-medium text-gray-300">Submitted:</span> {new Date(selected.created_at).toLocaleString()}</div>
                  {selected.reviewed_at && (
                    <div><span className="font-medium text-gray-300">Reviewed:</span> {new Date(selected.reviewed_at).toLocaleString()}</div>
                  )}
                </div>
              </ModalBody>
              <ModalFooter className="flex flex-wrap gap-2 justify-end">
                <Button variant="light" onPress={detail.onClose}>Close</Button>
                {(selected.status === 'pending' || selected.status === 'under_review') && (
                  <>
                    <Button
                      color="danger"
                      variant="flat"
                      startContent={<XCircle className="w-4 h-4" />}
                      isLoading={actionInFlight}
                      onPress={handleReject}
                    >
                      Reject
                    </Button>
                    <Button
                      color="success"
                      startContent={<CheckCircle2 className="w-4 h-4" />}
                      isLoading={actionInFlight}
                      onPress={handleApprove}
                    >
                      Approve & Provision
                    </Button>
                  </>
                )}
                {selected.status === 'failed' && (
                  <Button
                    color="warning"
                    startContent={<RefreshCw className="w-4 h-4" />}
                    isLoading={actionInFlight}
                    onPress={handleRetry}
                  >
                    Retry
                  </Button>
                )}
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default ProvisioningRequestsPage;
