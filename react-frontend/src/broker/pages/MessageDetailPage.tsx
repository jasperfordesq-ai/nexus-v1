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
import ArrowLeft from 'lucide-react/icons/arrow-left';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import Flag from 'lucide-react/icons/flag';
import Archive from 'lucide-react/icons/archive';
import Shield from 'lucide-react/icons/shield';
import MessageCircle from 'lucide-react/icons/message-circle';
import User from 'lucide-react/icons/user';
import Calendar from 'lucide-react/icons/calendar';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminBroker } from '@/admin/api/adminApi';
import { PageHeader } from '@/admin/components';
import type { BrokerMessageDetail, ConversationMessage } from '@/admin/api/types';

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
  const { t } = useTranslation('broker');
  usePageTitle(t('messages.detail_title'));
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
    const numericId = Number(id);
    if (!Number.isFinite(numericId) || numericId <= 0) {
      setError(t('messages.detail_invalid_id'));
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await adminBroker.showMessage(numericId);
      if (res.success && res.data) {
        setDetail(res.data);
      } else {
        setError(t('messages.detail_not_found'));
      }
    } catch {
      setError(t('messages.detail_load_failed'));
    } finally {
      setLoading(false);
    }
  }, [id, t])


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
        toast.success(t('messages.reviewed_success'));
        loadDetail();
      } else {
        toast.error(res?.error || t('messages.review_failed'));
      }
    } catch {
      toast.error(t('messages.review_failed'));
    } finally {
      setReviewLoading(false);
    }
  };

  const handleFlag = async () => {
    if (!id) return;
    if (!flagReason.trim()) {
      toast.error(t('messages.flag_reason_required'));
      return;
    }
    setFlagLoading(true);
    try {
      const res = await adminBroker.flagMessage(Number(id), flagReason, flagSeverity);
      if (res?.success) {
        toast.success(t('messages.flag_success'));
        setFlagModalOpen(false);
        setFlagReason('');
        setFlagSeverity('concern');
        loadDetail();
      } else {
        toast.error(res?.error || t('messages.flag_failed'));
      }
    } catch {
      toast.error(t('messages.flag_failed'));
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
        toast.success(t('messages.detail_approve_success'));
        setApproveModalOpen(false);
        navigate(tenantPath('/broker/messages'));
      } else {
        toast.error(res?.error || t('messages.detail_approve_failed'));
      }
    } catch {
      toast.error(t('messages.detail_approve_failed'));
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
        <p className="text-danger">{error || t('messages.detail_not_found')}</p>
        <Button
          as={Link}
          to={tenantPath('/broker/messages')}
          variant="flat"
          className="mt-4"
          startContent={<ArrowLeft className="w-4 h-4" />}
        >
          {t('messages.detail_back_to_messages')}
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
        title={t('messages.detail_page_title')}
        description={t('messages.detail_page_description')}
        actions={
          <Button
            as={Link}
            to={tenantPath('/broker/messages')}
            variant="flat"
            startContent={<ArrowLeft className="w-4 h-4" />}
            size="sm"
          >
            {t('messages.back')}
          </Button>
        }
      />

      {/* ── Metadata Card ──────────────────────────────────────────────────── */}
      <Card shadow="sm">
        <CardHeader className="flex items-center gap-2">
          <Shield className="w-4 h-4" />
          <span className="font-semibold">{t('messages.detail_metadata')}</span>
        </CardHeader>
        <Divider />
        <CardBody>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {/* Sender */}
            <div className="space-y-1">
              <p className="text-sm text-default-500 flex items-center gap-1">
                <User className="w-3 h-3" /> {t('messages.col_sender')}
              </p>
              <p className="text-sm font-medium text-foreground">{copy.sender_name}</p>
            </div>

            {/* Receiver */}
            <div className="space-y-1">
              <p className="text-sm text-default-500 flex items-center gap-1">
                <User className="w-3 h-3" /> {t('messages.col_receiver')}
              </p>
              <p className="text-sm font-medium text-foreground">{copy.receiver_name}</p>
            </div>

            {/* Listing */}
            <div className="space-y-1">
              <p className="text-sm text-default-500">{t('messages.detail_listing')}</p>
              <p className="text-sm text-foreground">
                {copy.listing_title || <span className="text-default-400">{t('messages.detail_none')}</span>}
              </p>
            </div>

            {/* Copy Reason */}
            <div className="space-y-1">
              <p className="text-sm text-default-500">{t('messages.detail_copy_reason')}</p>
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
                <Calendar className="w-3 h-3" /> {t('messages.detail_sent')}
              </p>
              <p className="text-sm text-foreground">
                {new Date(copy.sent_at).toLocaleString()}
              </p>
            </div>

            {/* Status Chips */}
            <div className="space-y-1">
              <p className="text-sm text-default-500">{t('messages.col_status')}</p>
              <div className="flex flex-wrap gap-1">
                {isFlagged && (
                  <Chip
                    size="sm"
                    variant="flat"
                    color="danger"
                    startContent={<Flag className="w-3 h-3" />}
                  >
                    {t('messages.flagged_label')}{copy.flag_severity ? ` (${copy.flag_severity})` : ''}
                  </Chip>
                )}
                {isReviewed && (
                  <Chip
                    size="sm"
                    variant="flat"
                    color="success"
                    startContent={<CheckCircle className="w-3 h-3" />}
                  >
                    {t('messages.status_reviewed')}
                  </Chip>
                )}
                {isArchived && (
                  <Chip
                    size="sm"
                    variant="flat"
                    color="secondary"
                    startContent={<Archive className="w-3 h-3" />}
                  >
                    {t('messages.detail_archived')}
                  </Chip>
                )}
                {!isFlagged && !isReviewed && !isArchived && (
                  <Chip size="sm" variant="flat" color="warning">
                    {t('messages.status_unreviewed')}
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
                <p className="text-sm text-default-500">{t('messages.detail_flag_reason')}</p>
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
          <span className="font-semibold">{t('messages.detail_conversation_thread')}</span>
          <Chip size="sm" variant="flat" className="ml-auto">
            {t('messages.detail_message_count', { count: thread.length })}
          </Chip>
        </CardHeader>
        <Divider />
        <CardBody className="p-0">
          {thread.length === 0 ? (
            <div className="p-6 text-center">
              <p className="text-sm text-default-500">{t('messages.detail_no_thread_messages')}</p>
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
                              {t('messages.detail_copied')}
                            </Chip>
                          )}
                          {msg.is_edited && (
                            <span className="text-xs text-default-400">{t('messages.detail_edited')}</span>
                          )}
                        </div>
                        <span className="text-xs text-default-500">
                          {new Date(msg.created_at).toLocaleString()}
                        </span>
                      </div>

                      {/* Subject line */}
                      {msg.subject && (
                        <p className="text-xs text-default-500 mb-1">
                          {t('messages.detail_subject')}: {msg.subject}
                        </p>
                      )}

                      {/* Message body */}
                      {msg.is_deleted ? (
                        <p className="text-sm italic text-default-400">
                          {t('messages.detail_message_deleted')}
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
            <span className="font-semibold">{t('messages.detail_archive_record')}</span>
          </CardHeader>
          <Divider />
          <CardBody>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              <div className="space-y-1">
                <p className="text-sm text-default-500">{t('messages.detail_decision')}</p>
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
                <p className="text-sm text-default-500">{t('messages.detail_decided_by')}</p>
                <p className="text-sm font-medium text-foreground">{archive.decided_by_name}</p>
              </div>
              <div className="space-y-1">
                <p className="text-sm text-default-500">{t('messages.detail_date')}</p>
                <p className="text-sm text-foreground">
                  {new Date(archive.decided_at).toLocaleString()}
                </p>
              </div>
            </div>
            {archive.decision_notes && (
              <>
                <Divider className="my-3" />
                <div className="space-y-1">
                  <p className="text-sm text-default-500">{t('messages.detail_notes')}</p>
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
          <span className="font-semibold">{t('messages.detail_actions')}</span>
        </CardHeader>
        <Divider />
        <CardBody>
          {isArchived ? (
            <p className="text-sm text-default-500">
              {t('messages.detail_archived_no_actions')}
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
                  {t('messages.detail_mark_reviewed')}
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
                  {t('messages.flag_action')}
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
                {t('messages.detail_approve_archive')}
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
            {t('messages.flag_modal_title')}
          </ModalHeader>
          <ModalBody>
            <Textarea
              label={t('messages.flag_reason_label')}
              placeholder={t('messages.flag_reason_placeholder')}
              value={flagReason}
              onValueChange={setFlagReason}
              minRows={3}
              variant="bordered"
              isRequired
            />
            <Select
              label={t('messages.severity_label')}
              selectedKeys={[flagSeverity]}
              onSelectionChange={(keys) => {
                const val = Array.from(keys)[0] as 'info' | 'warning' | 'concern' | 'urgent';
                if (val) setFlagSeverity(val);
              }}
              variant="bordered"
            >
              <SelectItem key="info">{t('messages.severity_info')}</SelectItem>
              <SelectItem key="warning">{t('messages.severity_warning')}</SelectItem>
              <SelectItem key="concern">{t('messages.severity_concern')}</SelectItem>
              <SelectItem key="urgent">{t('messages.severity_urgent')}</SelectItem>
            </Select>
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onPress={() => setFlagModalOpen(false)}
              isDisabled={flagLoading}
            >
              {t('messages.cancel')}
            </Button>
            <Button
              color="warning"
              onPress={handleFlag}
              isLoading={flagLoading}
              startContent={!flagLoading && <Flag className="w-4 h-4" />}
            >
              {t('messages.flag_action')}
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
            {t('messages.detail_approve_archive')}
          </ModalHeader>
          <ModalBody>
            <p className="text-sm text-default-600">
              {t('messages.detail_approve_warning')}
            </p>
            <Textarea
              label={t('messages.detail_decision_notes_label')}
              placeholder={t('messages.detail_decision_notes_placeholder')}
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
              {t('messages.cancel')}
            </Button>
            <Button
              color="primary"
              onPress={handleApprove}
              isLoading={approveLoading}
              startContent={!approveLoading && <Archive className="w-4 h-4" />}
            >
              {t('messages.detail_approve_confirm')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default MessageDetail;
