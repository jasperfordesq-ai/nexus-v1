// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * VerificationReviewQueue — Admin component for reviewing pending identity verification sessions.
 * Allows admins to approve or reject users awaiting manual review.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Card, CardBody, CardHeader, Button, Spinner, Chip,
  Table, TableHeader, TableColumn, TableBody, TableRow, TableCell,
  Modal, ModalContent, ModalHeader, ModalBody, ModalFooter, useDisclosure,
} from '@heroui/react';
import { ClipboardCheck, RefreshCw, CheckCircle, XCircle } from 'lucide-react';
import { api } from '@/lib/api';
import { useToast } from '@/contexts';

interface PendingSession {
  id: number;
  tenant_id: number;
  user_id: number;
  provider_slug: string;
  verification_level: string;
  status: string;
  created_at: string;
  completed_at: string | null;
  failure_reason: string | null;
  first_name: string | null;
  last_name: string | null;
  email: string | null;
}

type ReviewAction = 'approve' | 'reject';

const STATUS_COLORS: Record<string, 'default' | 'primary' | 'warning' | 'success' | 'danger'> = {
  created: 'default',
  started: 'primary',
  processing: 'warning',
  passed: 'success',
  failed: 'danger',
  expired: 'default',
  cancelled: 'default',
};

export function VerificationReviewQueue() {
  const toast = useToast();
  const [sessions, setSessions] = useState<PendingSession[]>([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);

  // Confirmation modal state
  const confirmModal = useDisclosure();
  const [selectedSession, setSelectedSession] = useState<PendingSession | null>(null);
  const [selectedAction, setSelectedAction] = useState<ReviewAction>('approve');

  const fetchSessions = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<PendingSession[]>('/v2/admin/identity/sessions?status=pending&limit=100');
      if (res.data) {
        setSessions(Array.isArray(res.data) ? res.data : []);
      }
    } catch {
      // silent — component may not have sessions
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchSessions();
  }, [fetchSessions]);

  const openConfirmation = (session: PendingSession, action: ReviewAction) => {
    setSelectedSession(session);
    setSelectedAction(action);
    confirmModal.onOpen();
  };

  const handleConfirm = async () => {
    if (!selectedSession) return;

    setActionLoading(true);
    try {
      const endpoint = `/v2/admin/identity/sessions/${selectedSession.id}/${selectedAction}`;
      const res = await api.post<{ status: string; message: string }>(endpoint, {});

      if (res.success) {
        toast.success(
          selectedAction === 'approve'
            ? 'User approved and activated successfully.'
            : 'User verification rejected.'
        );
        confirmModal.onClose();
        fetchSessions();
      } else {
        toast.error(res.error || `Failed to ${selectedAction} verification.`);
      }
    } catch {
      toast.error(`Failed to ${selectedAction} verification session.`);
    } finally {
      setActionLoading(false);
    }
  };

  const getUserName = (session: PendingSession): string => {
    return [session.first_name, session.last_name].filter(Boolean).join(' ') || `User #${session.user_id}`;
  };

  const formatDate = (dateStr: string): string => {
    return new Date(dateStr).toLocaleString();
  };

  const formatProvider = (slug: string): string => {
    return slug
      .split('_')
      .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
      .join(' ');
  };

  const formatLevel = (level: string): string => {
    return level
      .split('_')
      .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
      .join(' ');
  };

  return (
    <>
      <Card className="shadow-sm">
        <CardHeader className="flex flex-col sm:flex-row gap-3 justify-between items-start sm:items-center px-6 pt-5 pb-0">
          <div className="flex items-center gap-2">
            <ClipboardCheck className="w-5 h-5 text-amber-500" />
            <h3 className="text-lg font-semibold">Pending Verification Reviews</h3>
            {sessions.length > 0 && (
              <Chip size="sm" color="warning" variant="flat">
                {sessions.length} pending
              </Chip>
            )}
          </div>
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            onPress={fetchSessions}
            aria-label="Refresh pending reviews"
          >
            <RefreshCw className="w-4 h-4" />
          </Button>
        </CardHeader>
        <CardBody className="px-6 pb-5">
          {loading ? (
            <div className="flex justify-center py-8">
              <Spinner size="lg" />
            </div>
          ) : sessions.length === 0 ? (
            <p className="text-center py-8 text-theme-muted">
              No pending verification reviews. All users are up to date.
            </p>
          ) : (
            <Table aria-label="Pending verification reviews" removeWrapper>
              <TableHeader>
                <TableColumn>USER</TableColumn>
                <TableColumn>PROVIDER</TableColumn>
                <TableColumn>LEVEL</TableColumn>
                <TableColumn>STATUS</TableColumn>
                <TableColumn>CREATED</TableColumn>
                <TableColumn>ACTIONS</TableColumn>
              </TableHeader>
              <TableBody>
                {sessions.map((session) => (
                  <TableRow key={session.id}>
                    <TableCell>
                      <div className="text-sm font-medium">{getUserName(session)}</div>
                      {session.email && (
                        <div className="text-xs text-theme-muted">{session.email}</div>
                      )}
                    </TableCell>
                    <TableCell>
                      <span className="text-sm">{formatProvider(session.provider_slug)}</span>
                    </TableCell>
                    <TableCell>
                      <span className="text-sm">{formatLevel(session.verification_level)}</span>
                    </TableCell>
                    <TableCell>
                      <Chip
                        size="sm"
                        color={STATUS_COLORS[session.status] || 'default'}
                        variant="flat"
                      >
                        {session.status}
                      </Chip>
                    </TableCell>
                    <TableCell className="whitespace-nowrap text-xs text-theme-muted">
                      {formatDate(session.created_at)}
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        <Button
                          size="sm"
                          color="success"
                          variant="flat"
                          startContent={<CheckCircle className="w-3.5 h-3.5" />}
                          onPress={() => openConfirmation(session, 'approve')}
                        >
                          Approve
                        </Button>
                        <Button
                          size="sm"
                          color="danger"
                          variant="flat"
                          startContent={<XCircle className="w-3.5 h-3.5" />}
                          onPress={() => openConfirmation(session, 'reject')}
                        >
                          Reject
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardBody>
      </Card>

      {/* Confirmation Modal */}
      <Modal isOpen={confirmModal.isOpen} onOpenChange={confirmModal.onOpenChange} size="sm">
        <ModalContent>
          <ModalHeader>
            {selectedAction === 'approve' ? 'Approve User' : 'Reject User'}
          </ModalHeader>
          <ModalBody>
            {selectedSession && (
              <div className="space-y-2">
                <p className="text-sm">
                  {selectedAction === 'approve' ? (
                    <>
                      Are you sure you want to <strong className="text-success">approve</strong>{' '}
                      <strong>{getUserName(selectedSession)}</strong>? They will be activated and
                      granted full access to the platform.
                    </>
                  ) : (
                    <>
                      Are you sure you want to <strong className="text-danger">reject</strong>{' '}
                      <strong>{getUserName(selectedSession)}</strong>? Their verification will be
                      marked as failed and they will be notified.
                    </>
                  )}
                </p>
                {selectedSession.email && (
                  <p className="text-xs text-theme-muted">{selectedSession.email}</p>
                )}
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={confirmModal.onClose} isDisabled={actionLoading}>
              Cancel
            </Button>
            <Button
              color={selectedAction === 'approve' ? 'success' : 'danger'}
              onPress={handleConfirm}
              isLoading={actionLoading}
              startContent={
                !actionLoading
                  ? selectedAction === 'approve'
                    ? <CheckCircle className="w-4 h-4" />
                    : <XCircle className="w-4 h-4" />
                  : undefined
              }
            >
              {selectedAction === 'approve' ? 'Approve' : 'Reject'}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
}

export default VerificationReviewQueue;
