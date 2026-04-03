// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GDPR Request Detail
 * Detail page for viewing and managing a single GDPR request.
 * Route: /admin/enterprise/gdpr/requests/:id
 */

import { useEffect, useState, useCallback, useMemo } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Card, CardBody, CardHeader, Chip, Button, Spinner,
  Modal, ModalContent, ModalHeader, ModalBody, ModalFooter,
  Textarea, Input, Progress, Divider,
} from '@heroui/react';
import {
  ArrowLeft, MessageSquarePlus, UserPlus, Download,
  Play, CheckCircle, XCircle, Clock, AlertTriangle,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader, StatusBadge } from '../../components';
import type { GdprRequestDetail as GdprRequestDetailType, GdprTimelineEntry } from '../../api/types';

import { useTranslation } from 'react-i18next';

const typeColorMap: Record<string, 'default' | 'primary' | 'secondary' | 'success' | 'warning' | 'danger'> = {
  access: 'primary',
  erasure: 'danger',
  portability: 'secondary',
  rectification: 'warning',
  restriction: 'default',
  objection: 'danger',
};

const actionColorMap: Record<string, 'default' | 'primary' | 'success' | 'warning' | 'danger'> = {
  created: 'primary',
  processing: 'warning',
  completed: 'success',
  rejected: 'danger',
  assigned: 'default',
  note_added: 'default',
  exported: 'success',
};

export function GdprRequestDetail() {
  useTranslation('admin');
  const { id } = useParams();
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();

  usePageTitle(`GDPR Request #${id}`);

  const [request, setRequest] = useState<GdprRequestDetailType | null>(null);
  const [loading, setLoading] = useState(true);

  // Note modal
  const [noteOpen, setNoteOpen] = useState(false);
  const [noteText, setNoteText] = useState('');
  const [noteLoading, setNoteLoading] = useState(false);

  // Assign modal
  const [assignOpen, setAssignOpen] = useState(false);
  const [assignUserId, setAssignUserId] = useState('');
  const [assignLoading, setAssignLoading] = useState(false);

  // Rejection modal
  const [rejectOpen, setRejectOpen] = useState(false);
  const [rejectionReason, setRejectionReason] = useState('');
  const [rejectLoading, setRejectLoading] = useState(false);

  const [actionLoading, setActionLoading] = useState(false);

  const requestId = useMemo(() => (id ? parseInt(id, 10) : 0), [id]);

  const loadData = useCallback(async () => {
    if (!requestId) return;
    setLoading(true);
    try {
      const res = await adminEnterprise.getGdprRequest(requestId);
      if (res.success && res.data) {
        setRequest(res.data as unknown as GdprRequestDetailType);
      }
    } catch {
      toast.error('Failed to load GDPR request');
    } finally {
      setLoading(false);
    }
  }, [requestId, toast]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleStatusUpdate = async (newStatus: string) => {
    setActionLoading(true);
    try {
      const res = await adminEnterprise.updateGdprRequest(requestId, { status: newStatus });
      if (res.success) {
        toast.success(`Request marked as ${newStatus}`);
        loadData();
      } else {
        toast.error('Failed to update request status');
      }
    } catch {
      toast.error('Failed to update request status');
    } finally {
      setActionLoading(false);
    }
  };

  const handleAddNote = async () => {
    if (!noteText.trim()) {
      toast.error('Note cannot be empty');
      return;
    }
    setNoteLoading(true);
    try {
      const res = await adminEnterprise.addGdprRequestNote(requestId, noteText.trim());
      if (res.success) {
        toast.success('Note added successfully');
        setNoteOpen(false);
        setNoteText('');
        loadData();
      } else {
        toast.error('Failed to add note');
      }
    } catch {
      toast.error('Failed to add note');
    } finally {
      setNoteLoading(false);
    }
  };

  const handleAssign = async () => {
    const userId = parseInt(assignUserId, 10);
    if (!userId || isNaN(userId)) {
      toast.error('Please enter a valid user ID');
      return;
    }
    setAssignLoading(true);
    try {
      const res = await adminEnterprise.assignGdprRequest(requestId, userId);
      if (res.success) {
        toast.success('Request assigned successfully');
        setAssignOpen(false);
        setAssignUserId('');
        loadData();
      } else {
        toast.error('Failed to assign request');
      }
    } catch {
      toast.error('Failed to assign request');
    } finally {
      setAssignLoading(false);
    }
  };

  const handleReject = async () => {
    if (!rejectionReason.trim()) {
      toast.error('Rejection reason is required');
      return;
    }
    setRejectLoading(true);
    try {
      const res = await adminEnterprise.updateGdprRequest(requestId, {
        status: 'rejected',
        notes: rejectionReason.trim(),
      });
      if (res.success) {
        toast.success('Request rejected');
        setRejectOpen(false);
        setRejectionReason('');
        loadData();
      } else {
        toast.error('Failed to reject request');
      }
    } catch {
      toast.error('Failed to reject request');
    } finally {
      setRejectLoading(false);
    }
  };

  const handleExport = async () => {
    setActionLoading(true);
    try {
      const res = await adminEnterprise.generateGdprExport(requestId);
      if (res.success) {
        toast.success('Export generated successfully');
        loadData();
      } else {
        toast.error('Failed to generate export');
      }
    } catch {
      toast.error('Failed to generate export');
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

  if (!request) {
    return (
      <div className="text-center py-16">
        <p className="text-default-500">Request not found</p>
        <Button
          variant="flat"
          className="mt-4"
          onPress={() => navigate(tenantPath('/admin/enterprise/gdpr/requests'))}
        >
          Back to Requests
        </Button>
      </div>
    );
  }

  const slaDeadline = new Date(request.sla_deadline);
  const slaProgress = Math.min(100, Math.max(0, ((30 - request.sla_days_remaining) / 30) * 100));
  const slaColor: 'success' | 'warning' | 'danger' =
    request.sla_days_remaining > 7 ? 'success' : request.sla_days_remaining > 0 ? 'warning' : 'danger';

  return (
    <div>
      <PageHeader
        title={`GDPR Request #${request.id}`}
        description={`${request.type} request from ${request.user_name}`}
        actions={
          <Button
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            onPress={() => navigate(tenantPath('/admin/enterprise/gdpr/requests'))}
            size="sm"
          >
            Back to Requests
          </Button>
        }
      />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Main Column */}
        <div className="lg:col-span-2 space-y-6">
          {/* Request Info Card */}
          <Card shadow="sm">
            <CardHeader className="px-4 pt-4 pb-0">
              <h3 className="text-lg font-semibold">Request Information</h3>
            </CardHeader>
            <CardBody className="p-4 space-y-4">
              <div className="flex flex-wrap gap-3">
                <Chip
                  size="sm"
                  variant="flat"
                  color={typeColorMap[request.type] || 'default'}
                  className="capitalize"
                >
                  {request.type}
                </Chip>
                <StatusBadge status={request.status} />
                {request.priority && (
                  <Chip size="sm" variant="bordered" className="capitalize">
                    {request.priority} priority
                  </Chip>
                )}
              </div>

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                  <p className="text-sm text-default-500">User</p>
                  <p className="font-medium">{request.user_name}</p>
                  {request.user_email && (
                    <p className="text-sm text-default-400">{request.user_email}</p>
                  )}
                </div>
                <div>
                  <p className="text-sm text-default-500">User ID</p>
                  <p className="font-medium">{request.user_id}</p>
                </div>
                <div>
                  <p className="text-sm text-default-500">Created</p>
                  <p className="font-medium">{new Date(request.created_at).toLocaleString()}</p>
                </div>
                {request.completed_at && (
                  <div>
                    <p className="text-sm text-default-500">Completed</p>
                    <p className="font-medium">{new Date(request.completed_at).toLocaleString()}</p>
                  </div>
                )}
              </div>

              {request.rejection_reason && (
                <div className="p-3 rounded-lg bg-danger-50 border border-danger-200">
                  <p className="text-sm font-medium text-danger">Rejection Reason</p>
                  <p className="text-sm text-danger-700 mt-1">{request.rejection_reason}</p>
                </div>
              )}
            </CardBody>
          </Card>

          {/* Notes Section Card */}
          <Card shadow="sm">
            <CardHeader className="px-4 pt-4 pb-0 flex justify-between items-center">
              <h3 className="text-lg font-semibold">Notes</h3>
              <Button
                size="sm"
                color="primary"
                variant="flat"
                startContent={<MessageSquarePlus size={14} />}
                onPress={() => setNoteOpen(true)}
              >
                Add Note
              </Button>
            </CardHeader>
            <CardBody className="p-4">
              {request.notes ? (
                <div className="whitespace-pre-wrap text-sm text-default-700 bg-default-50 rounded-lg p-3">
                  {request.notes}
                </div>
              ) : (
                <p className="text-sm text-default-400 italic">No notes yet</p>
              )}
            </CardBody>
          </Card>

          {/* Activity Timeline Card */}
          <Card shadow="sm">
            <CardHeader className="px-4 pt-4 pb-0">
              <h3 className="text-lg font-semibold">Activity Timeline</h3>
            </CardHeader>
            <CardBody className="p-4">
              {request.timeline && request.timeline.length > 0 ? (
                <div className="relative pl-6">
                  {/* Vertical line */}
                  <div className="absolute left-2 top-1 bottom-1 w-0.5 bg-default-200" />

                  <div className="space-y-4">
                    {request.timeline.map((entry: GdprTimelineEntry) => (
                      <div key={entry.id} className="relative">
                        {/* Dot indicator */}
                        <div className="absolute -left-4 top-1.5 h-3 w-3 rounded-full border-2 border-default-300 bg-background" />
                        <div className="flex flex-col gap-1">
                          <div className="flex items-center gap-2 flex-wrap">
                            <span className="text-xs text-default-400">
                              {new Date(entry.created_at).toLocaleString()}
                            </span>
                            <Chip
                              size="sm"
                              variant="flat"
                              color={actionColorMap[entry.action] || 'default'}
                              className="capitalize"
                            >
                              {entry.action.replace(/_/g, ' ')}
                            </Chip>
                            {entry.user_name && (
                              <span className="text-sm text-default-600">by {entry.user_name}</span>
                            )}
                          </div>
                          {(entry.old_value || entry.new_value) && (
                            <p className="text-sm text-default-500">
                              {entry.old_value && <span className="line-through mr-2">{entry.old_value}</span>}
                              {entry.new_value && <span>{entry.new_value}</span>}
                            </p>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              ) : (
                <p className="text-sm text-default-400 italic">No activity recorded</p>
              )}
            </CardBody>
          </Card>
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* SLA Status Card */}
          <Card shadow="sm">
            <CardHeader className="px-4 pt-4 pb-0">
              <h3 className="text-lg font-semibold flex items-center gap-2">
                <Clock size={18} />
                SLA Status
              </h3>
            </CardHeader>
            <CardBody className="p-4 space-y-3">
              <div>
                <p className="text-sm text-default-500">Deadline</p>
                <p className="font-medium">{slaDeadline.toLocaleDateString()}</p>
              </div>
              <div>
                <p className="text-sm text-default-500 mb-1">
                  {request.sla_overdue
                    ? `Overdue by ${Math.abs(request.sla_days_remaining)} days`
                    : `${request.sla_days_remaining} days remaining`}
                </p>
                <Progress
                  value={slaProgress}
                  color={slaColor}
                  size="sm"
                  aria-label="SLA progress"
                />
              </div>
              {request.sla_overdue && (
                <Chip size="sm" color="danger" variant="flat" startContent={<AlertTriangle size={12} />}>
                  SLA Breached
                </Chip>
              )}
            </CardBody>
          </Card>

          {/* Assignment Card */}
          <Card shadow="sm">
            <CardHeader className="px-4 pt-4 pb-0">
              <h3 className="text-lg font-semibold flex items-center gap-2">
                <UserPlus size={18} />
                Assignment
              </h3>
            </CardHeader>
            <CardBody className="p-4 space-y-3">
              <div>
                <p className="text-sm text-default-500">Assigned To</p>
                <p className="font-medium">
                  {request.assigned_to_name || 'Unassigned'}
                </p>
              </div>
              <Button
                size="sm"
                color="primary"
                variant="flat"
                startContent={<UserPlus size={14} />}
                onPress={() => setAssignOpen(true)}
                className="w-full"
              >
                {request.assigned_to ? 'Reassign' : 'Assign'}
              </Button>
            </CardBody>
          </Card>

          {/* Actions Card */}
          <Card shadow="sm">
            <CardHeader className="px-4 pt-4 pb-0">
              <h3 className="text-lg font-semibold">Actions</h3>
            </CardHeader>
            <CardBody className="p-4 space-y-2">
              {request.status === 'pending' && (
                <Button
                  color="primary"
                  variant="flat"
                  startContent={<Play size={14} />}
                  onPress={() => handleStatusUpdate('processing')}
                  isLoading={actionLoading}
                  className="w-full"
                  size="sm"
                >
                  Start Processing
                </Button>
              )}
              {request.status === 'processing' && (
                <>
                  <Button
                    color="success"
                    variant="flat"
                    startContent={<CheckCircle size={14} />}
                    onPress={() => handleStatusUpdate('completed')}
                    isLoading={actionLoading}
                    className="w-full"
                    size="sm"
                  >
                    Mark Complete
                  </Button>
                  <Button
                    color="danger"
                    variant="flat"
                    startContent={<XCircle size={14} />}
                    onPress={() => setRejectOpen(true)}
                    className="w-full"
                    size="sm"
                  >
                    Reject
                  </Button>
                </>
              )}
              <Divider />
              <Button
                color="secondary"
                variant="flat"
                startContent={<Download size={14} />}
                onPress={handleExport}
                isLoading={actionLoading}
                className="w-full"
                size="sm"
              >
                Generate Export
              </Button>
              {request.export_file_path && (
                <p className="text-xs text-success text-center">Export available</p>
              )}
            </CardBody>
          </Card>
        </div>
      </div>

      {/* Add Note Modal */}
      <Modal isOpen={noteOpen} onClose={() => setNoteOpen(false)} size="lg">
        <ModalContent>
          <ModalHeader>Add Note</ModalHeader>
          <ModalBody>
            <Textarea
              label="Note"
              placeholder="Enter your note..."
              value={noteText}
              onValueChange={setNoteText}
              variant="bordered"
              minRows={4}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setNoteOpen(false)} isDisabled={noteLoading}>
              Cancel
            </Button>
            <Button color="primary" onPress={handleAddNote} isLoading={noteLoading}>
              Add Note
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Assign Modal */}
      <Modal isOpen={assignOpen} onClose={() => setAssignOpen(false)}>
        <ModalContent>
          <ModalHeader>Assign Request</ModalHeader>
          <ModalBody>
            <Input
              label="User ID"
              placeholder="Enter user ID to assign"
              type="number"
              value={assignUserId}
              onValueChange={setAssignUserId}
              variant="bordered"
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setAssignOpen(false)} isDisabled={assignLoading}>
              Cancel
            </Button>
            <Button color="primary" onPress={handleAssign} isLoading={assignLoading}>
              Assign
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Rejection Reason Modal */}
      <Modal isOpen={rejectOpen} onClose={() => setRejectOpen(false)} size="lg">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <XCircle size={20} className="text-danger" />
            Reject Request
          </ModalHeader>
          <ModalBody>
            <Textarea
              label="Rejection Reason"
              placeholder="Provide a reason for rejecting this request..."
              value={rejectionReason}
              onValueChange={setRejectionReason}
              variant="bordered"
              minRows={3}
              isRequired
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setRejectOpen(false)} isDisabled={rejectLoading}>
              Cancel
            </Button>
            <Button color="danger" onPress={handleReject} isLoading={rejectLoading}>
              Reject Request
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default GdprRequestDetail;
