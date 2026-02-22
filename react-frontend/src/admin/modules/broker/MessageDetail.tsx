// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Message Detail
 * Broker message copy detail view with full conversation thread and moderation actions.
 * Parity: PHP BrokerControlsController::showMessage()
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import {
  Card, CardBody, CardHeader,
  Button, Chip, Divider,
  Modal, ModalContent, ModalHeader, ModalBody, ModalFooter,
  Textarea, Select, SelectItem,
  ScrollShadow, Spinner,
} from '@heroui/react';
import {
  ArrowLeft, CheckCircle, Flag, Archive, Shield,
  MessageCircle, User, Calendar, AlertTriangle,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminBroker } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { BrokerMessageDetail, ConversationMessage } from '../../api/types';

// ─── Copy reason chip colors ──────────────────────────────────────────────────

const COPY_REASON_COLORS: Record<string, 'primary' | 'danger' | 'success' | 'warning' | 'secondary' | 'default'> = {
  first_contact: 'primary',
  high_risk_listing: 'danger',
  new_member: 'success',
  flagged_user: 'warning',
  manual_monitoring: 'secondary',
  random_sample: 'default',
};

function formatCopyReason(reason: string): string {
  return reason.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

// ─── Component ────────────────────────────────────────────────────────────────

export function MessageDetail() {
  usePageTitle('Admin - Message Detail');
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();

  // Data state
  const [detail, setDetail] = useState<BrokerMessageDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Action loading states
  const [reviewLoading, setReviewLoading] = useState(false);
  const [approveLoading, setApproveLoading] = useState(false);

  // Flag modal state
  const [flagModalOpen, setFlagModalOpen] = useState(false);
  const [flagReason, setFlagReason] = useState('');
  const [flagSeverity, setFlagSeverity] = useState<'info' | 'warning' | 'concern' | 'urgent'>('concern');
  const [flagLoading, setFlagLoading] = useState(false);

  // Approve modal state
  const [approveModalOpen, setApproveModalOpen] = useState(false);
  const [approveNotes, setApproveNotes] = useState('');

  // ── Load data ─────────────────────────────────────────────────────────────

  const loadDetail = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const res = await adminBroker.showMessage(Number(id));
      if (res.success && res.data) {
        setDetail(res.data);
      } else {
        setError('Message not found');
      }
    } catch {
      setError('Failed to load message details');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadDetail();
  }, [loadDetail]);

  // ── Actions ───────────────────────────────────────────────────────────────

  const handleReview = async () => {
    if (!id) return;
    setReviewLoading(true);
    try {
      const res = await adminBroker.reviewMessage(Number(id));
      if (res?.success) {
        toast.success('Message marked as reviewed');
        loadDetail();
      } else {
        toast.error(res?.error || 'Failed to mark message as reviewed');
      }
    } catch {
      toast.error('Failed to mark message as reviewed');
    } finally {
      setReviewLoading(false);
    }
  };

  const handleFlag = async () => {
    if (!id) return;
    if (!flagReason.trim()) {
      toast.error('A reason is required to flag a message');
      return;
    }
    setFlagLoading(true);
    try {
      const res = await adminBroker.flagMessage(Number(id), flagReason, flagSeverity);
      if (res?.success) {
        toast.success('Message flagged successfully');
        setFlagModalOpen(false);
        setFlagReason('');
        setFlagSeverity('concern');
        loadDetail();
      } else {
        toast.error(res?.error || 'Failed to flag message');
      }
    } catch {
      toast.error('Failed to flag message');
    } finally {
      setFlagLoading(false);
    }
  };

  const handleApprove = async () => {
    if (!id) return;
    setApproveLoading(true);
    try {
      const res = await adminBroker.approveMessage(Number(id), approveNotes || undefined);
      if (res?.success) {
        toast.success('Message approved and archived');
        setApproveModalOpen(false);
        navigate(tenantPath('/admin/broker-controls/messages'));
      } else {
        toast.error(res?.error || 'Failed to approve message');
      }
    } catch {
      toast.error('Failed to approve message');
    } finally {
      setApproveLoading(false);
    }
  };

  // ── Loading state ─────────────────────────────────────────────────────────

  if (loading) {
    return (
      <div className="flex justify-center items-center min-h-[300px]">
        <Spinner size="lg" />
      </div>
    );
  }

  // ── Error state ───────────────────────────────────────────────────────────

  if (error || !detail) {
    return (
      <div className="text-center py-12">
        <p className="text-danger">{error || 'Message not found'}</p>
        <Button
          as={Link}
          to={tenantPath('/admin/broker-controls/messages')}
          variant="flat"
          className="mt-4"
          startContent={<ArrowLeft className="w-4 h-4" />}
        >
          Back to Messages
        </Button>
      </div>
    );
  }

  const { copy, thread, archive } = detail;
  const isArchived = archive !== null;
  const isReviewed = !!copy.reviewed_at;
  const isFlagged = copy.flagged;

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <PageHeader
        title="Message Review"
        description={`Copy #${copy.id} — ${copy.sender_name} to ${copy.receiver_name}`}
        actions={
          <Button
            as={Link}
            to={tenantPath('/admin/broker-controls/messages')}
            variant="flat"
            startContent={<ArrowLeft className="w-4 h-4" />}
            size="sm"
          >
            Back
          </Button>
        }
      />

      {/* ── Metadata Card ──────────────────────────────────────────────────── */}
      <Card shadow="sm">
        <CardHeader className="flex items-center gap-2">
          <Shield className="w-4 h-4" />
          <span className="font-semibold">Message Metadata</span>
        </CardHeader>
        <Divider />
        <CardBody>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {/* Sender */}
            <div className="space-y-1">
              <p className="text-sm text-default-500 flex items-center gap-1">
                <User className="w-3 h-3" /> Sender
              </p>
              <p className="text-sm font-medium text-foreground">{copy.sender_name}</p>
            </div>

            {/* Receiver */}
            <div className="space-y-1">
              <p className="text-sm text-default-500 flex items-center gap-1">
                <User className="w-3 h-3" /> Receiver
              </p>
              <p className="text-sm font-medium text-foreground">{copy.receiver_name}</p>
            </div>

            {/* Listing */}
            <div className="space-y-1">
              <p className="text-sm text-default-500">Listing</p>
              <p className="text-sm text-foreground">
                {copy.listing_title || <span className="text-default-400">None</span>}
              </p>
            </div>

            {/* Copy Reason */}
            <div className="space-y-1">
              <p className="text-sm text-default-500">Copy Reason</p>
              <Chip
                size="sm"
                variant="flat"
                color={COPY_REASON_COLORS[copy.copy_reason] ?? 'default'}
              >
                {formatCopyReason(copy.copy_reason)}
              </Chip>
            </div>

            {/* Sent At */}
            <div className="space-y-1">
              <p className="text-sm text-default-500 flex items-center gap-1">
                <Calendar className="w-3 h-3" /> Sent
              </p>
              <p className="text-sm text-foreground">
                {new Date(copy.sent_at).toLocaleString()}
              </p>
            </div>

            {/* Status Chips */}
            <div className="space-y-1">
              <p className="text-sm text-default-500">Status</p>
              <div className="flex flex-wrap gap-1">
                {isFlagged && (
                  <Chip
                    size="sm"
                    variant="flat"
                    color="danger"
                    startContent={<Flag className="w-3 h-3" />}
                  >
                    Flagged{copy.flag_severity ? ` (${copy.flag_severity})` : ''}
                  </Chip>
                )}
                {isReviewed && (
                  <Chip
                    size="sm"
                    variant="flat"
                    color="success"
                    startContent={<CheckCircle className="w-3 h-3" />}
                  >
                    Reviewed
                  </Chip>
                )}
                {isArchived && (
                  <Chip
                    size="sm"
                    variant="flat"
                    color="secondary"
                    startContent={<Archive className="w-3 h-3" />}
                  >
                    Archived
                  </Chip>
                )}
                {!isFlagged && !isReviewed && !isArchived && (
                  <Chip size="sm" variant="flat" color="warning">
                    Unreviewed
                  </Chip>
                )}
              </div>
            </div>
          </div>

          {/* Flag reason details */}
          {isFlagged && copy.flag_reason && (
            <>
              <Divider className="my-3" />
              <div className="space-y-1">
                <p className="text-sm text-default-500">Flag Reason</p>
                <p className="text-sm text-foreground">{copy.flag_reason}</p>
              </div>
            </>
          )}
        </CardBody>
      </Card>

      {/* ── Conversation Thread ────────────────────────────────────────────── */}
      <Card shadow="sm">
        <CardHeader className="flex items-center gap-2">
          <MessageCircle className="w-4 h-4" />
          <span className="font-semibold">Conversation Thread</span>
          <Chip size="sm" variant="flat" className="ml-auto">
            {thread.length} message{thread.length !== 1 ? 's' : ''}
          </Chip>
        </CardHeader>
        <Divider />
        <CardBody className="p-0">
          {thread.length === 0 ? (
            <div className="p-6 text-center">
              <p className="text-sm text-default-500">No messages in thread</p>
            </div>
          ) : (
            <ScrollShadow className="max-h-[500px]">
              <div className="divide-y divide-default-200">
                {thread.map((msg: ConversationMessage) => {
                  const isTarget = msg.id === copy.original_message_id;
                  return (
                    <div
                      key={msg.id}
                      className={`p-4 ${
                        isTarget
                          ? 'border-l-4 border-warning bg-warning-50/50 dark:bg-warning-50/10'
                          : ''
                      }`}
                    >
                      {/* Message header */}
                      <div className="flex items-center justify-between mb-1">
                        <div className="flex items-center gap-2">
                          <span className="text-sm font-semibold text-foreground">
                            {msg.sender_name}
                          </span>
                          {isTarget && (
                            <Chip size="sm" variant="flat" color="warning">
                              <AlertTriangle className="w-3 h-3 mr-1 inline" />
                              Copied Message
                            </Chip>
                          )}
                          {msg.is_edited && (
                            <span className="text-xs text-default-400">(edited)</span>
                          )}
                        </div>
                        <span className="text-xs text-default-500">
                          {new Date(msg.created_at).toLocaleString()}
                        </span>
                      </div>

                      {/* Subject line */}
                      {msg.subject && (
                        <p className="text-xs text-default-500 mb-1">
                          Subject: {msg.subject}
                        </p>
                      )}

                      {/* Message body */}
                      {msg.is_deleted ? (
                        <p className="text-sm italic text-default-400">
                          This message has been deleted
                        </p>
                      ) : (
                        <p className="text-sm text-foreground whitespace-pre-wrap">
                          {msg.body}
                        </p>
                      )}
                    </div>
                  );
                })}
              </div>
            </ScrollShadow>
          )}
        </CardBody>
      </Card>

      {/* ── Archive Info (if archived) ─────────────────────────────────────── */}
      {isArchived && archive && (
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2">
            <Archive className="w-4 h-4 text-secondary" />
            <span className="font-semibold">Archive Record</span>
          </CardHeader>
          <Divider />
          <CardBody>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              <div className="space-y-1">
                <p className="text-sm text-default-500">Decision</p>
                <Chip
                  size="sm"
                  variant="flat"
                  color={archive.decision === 'approved' ? 'success' : 'danger'}
                  className="capitalize"
                >
                  {archive.decision}
                </Chip>
              </div>
              <div className="space-y-1">
                <p className="text-sm text-default-500">Decided By</p>
                <p className="text-sm font-medium text-foreground">{archive.decided_by_name}</p>
              </div>
              <div className="space-y-1">
                <p className="text-sm text-default-500">Date</p>
                <p className="text-sm text-foreground">
                  {new Date(archive.decided_at).toLocaleString()}
                </p>
              </div>
            </div>
            {archive.decision_notes && (
              <>
                <Divider className="my-3" />
                <div className="space-y-1">
                  <p className="text-sm text-default-500">Notes</p>
                  <p className="text-sm text-foreground">{archive.decision_notes}</p>
                </div>
              </>
            )}
          </CardBody>
        </Card>
      )}

      {/* ── Actions ────────────────────────────────────────────────────────── */}
      <Card shadow="sm">
        <CardHeader className="flex items-center gap-2">
          <Shield className="w-4 h-4" />
          <span className="font-semibold">Actions</span>
        </CardHeader>
        <Divider />
        <CardBody>
          {isArchived ? (
            <p className="text-sm text-default-500">
              This message has been archived. No further actions are available.
            </p>
          ) : (
            <div className="flex flex-wrap gap-3">
              {/* Mark Reviewed */}
              {!isReviewed && (
                <Button
                  color="success"
                  variant="flat"
                  startContent={!reviewLoading && <CheckCircle className="w-4 h-4" />}
                  onPress={handleReview}
                  isLoading={reviewLoading}
                >
                  Mark Reviewed
                </Button>
              )}

              {/* Flag */}
              {!isFlagged && (
                <Button
                  color="warning"
                  variant="flat"
                  startContent={<Flag className="w-4 h-4" />}
                  onPress={() => {
                    setFlagReason('');
                    setFlagSeverity('concern');
                    setFlagModalOpen(true);
                  }}
                >
                  Flag
                </Button>
              )}

              {/* Approve & Archive */}
              <Button
                color="primary"
                startContent={<Archive className="w-4 h-4" />}
                onPress={() => {
                  setApproveNotes('');
                  setApproveModalOpen(true);
                }}
              >
                Approve &amp; Archive
              </Button>
            </div>
          )}
        </CardBody>
      </Card>

      {/* ── Flag Modal ─────────────────────────────────────────────────────── */}
      <Modal
        isOpen={flagModalOpen}
        onClose={() => setFlagModalOpen(false)}
        size="md"
      >
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <Flag className="w-5 h-5 text-warning" />
            Flag Message
          </ModalHeader>
          <ModalBody>
            <Textarea
              label="Reason (required)"
              placeholder="Describe why this message is being flagged..."
              value={flagReason}
              onValueChange={setFlagReason}
              minRows={3}
              variant="bordered"
              isRequired
            />
            <Select
              label="Severity"
              selectedKeys={[flagSeverity]}
              onSelectionChange={(keys) => {
                const val = Array.from(keys)[0] as 'info' | 'warning' | 'concern' | 'urgent';
                if (val) setFlagSeverity(val);
              }}
              variant="bordered"
            >
              <SelectItem key="info">Info</SelectItem>
              <SelectItem key="warning">Warning</SelectItem>
              <SelectItem key="concern">Concern</SelectItem>
              <SelectItem key="urgent">Urgent</SelectItem>
            </Select>
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onPress={() => setFlagModalOpen(false)}
              isDisabled={flagLoading}
            >
              Cancel
            </Button>
            <Button
              color="warning"
              onPress={handleFlag}
              isLoading={flagLoading}
              startContent={!flagLoading && <Flag className="w-4 h-4" />}
            >
              Flag Message
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* ── Approve & Archive Modal ────────────────────────────────────────── */}
      <Modal
        isOpen={approveModalOpen}
        onClose={() => setApproveModalOpen(false)}
        size="md"
      >
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <Archive className="w-5 h-5 text-primary" />
            Approve &amp; Archive
          </ModalHeader>
          <ModalBody>
            <p className="text-sm text-default-600">
              Approving this message will create an immutable compliance record.
              This action cannot be undone.
            </p>
            <Textarea
              label="Decision Notes (optional)"
              placeholder="Add any notes about this review decision..."
              value={approveNotes}
              onValueChange={setApproveNotes}
              minRows={3}
              variant="bordered"
            />
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onPress={() => setApproveModalOpen(false)}
              isDisabled={approveLoading}
            >
              Cancel
            </Button>
            <Button
              color="primary"
              onPress={handleApprove}
              isLoading={approveLoading}
              startContent={!approveLoading && <Archive className="w-4 h-4" />}
            >
              Confirm Approve
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default MessageDetail;
