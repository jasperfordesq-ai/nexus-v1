// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Match Detail Admin Page
 * Displays full details of a single match approval.
 * Replaces the AdminPlaceholder for /admin/match-approvals/:id.
 */

import { useState, useEffect, useCallback } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Chip,
  Avatar,
  Spinner,
  Progress,
  Textarea,
  Divider,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
} from '@heroui/react';
import {
  ArrowLeft,
  CheckCircle,
  XCircle,
  MapPin,
  User,
  FileText,
  Clock,
  Shield,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminMatching } from '../../api/adminApi';
import { PageHeader, StatusBadge } from '../../components';
import type { MatchApprovalDetail } from '../../api/types';

import { useTranslation } from 'react-i18next';
// Score color helper
function scoreColor(score: number): 'danger' | 'warning' | 'success' {
  if (score < 50) return 'danger';
  if (score < 75) return 'warning';
  return 'success';
}

// Score label key
function scoreLabelKey(score: number): string {
  if (score >= 90) return 'matching.score_excellent';
  if (score >= 75) return 'matching.score_good';
  if (score >= 50) return 'matching.score_fair';
  return 'matching.score_low';
}

export function MatchDetail() {
  const { t } = useTranslation('admin');
  usePageTitle(t('matching.page_title'));
  const toast = useToast();
  const { tenantPath } = useTenant();
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();

  const [item, setItem] = useState<MatchApprovalDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Action state
  const [approveLoading, setApproveLoading] = useState(false);
  const [rejectModal, setRejectModal] = useState(false);
  const [rejectReason, setRejectReason] = useState('');
  const [rejectLoading, setRejectLoading] = useState(false);

  const loadItem = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    const res = await adminMatching.getApproval(Number(id));
    if (res.success && res.data) {
      const data = res.data as unknown;
      if (data && typeof data === 'object' && 'data' in data) {
        setItem((data as { data: MatchApprovalDetail }).data);
      } else {
        setItem(data as MatchApprovalDetail);
      }
    } else {
      setError(res.error || t('matching.failed_to_load_match_approval'));
    }
    setLoading(false);
  }, [id, t]);

  useEffect(() => {
    loadItem();
  }, [loadItem]);

  const handleApprove = async () => {
    if (!item) return;
    setApproveLoading(true);
    const res = await adminMatching.approveMatch(item.id);
    if (res.success) {
      toast.success(t('matching.match_approved', { id: item.id }));
      loadItem();
    } else {
      toast.error(res.error || t('matching.failed_to_approve_match'));
    }
    setApproveLoading(false);
  };

  const handleReject = async () => {
    if (!item) return;
    if (!rejectReason.trim()) {
      toast.error(t('matching.please_provide_a_reason_for_rejection'));
      return;
    }
    setRejectLoading(true);
    const res = await adminMatching.rejectMatch(item.id, rejectReason.trim());
    if (res.success) {
      toast.success(t('matching.match_rejected', { id: item.id }));
      setRejectModal(false);
      setRejectReason('');
      loadItem();
    } else {
      toast.error(res.error || t('matching.failed_to_reject_match'));
    }
    setRejectLoading(false);
  };

  // Loading state
  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Spinner size="lg" />
      </div>
    );
  }

  // Error state
  if (error || !item) {
    return (
      <div>
        <PageHeader
          title={t('matching.match_detail_title')}
          actions={
            <Button
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(tenantPath('/admin/match-approvals'))}
            >
              {t('matching.back')}
            </Button>
          }
        />
        <Card shadow="sm">
          <CardBody className="flex flex-col items-center justify-center py-16">
            <XCircle size={40} className="mb-3 text-danger" />
            <p className="text-lg font-medium text-foreground">
              {t('matching.match_not_found')}
            </p>
            <p className="mt-1 text-sm text-default-500">
              {error || t('matching.match_could_not_be_loaded')}
            </p>
          </CardBody>
        </Card>
      </div>
    );
  }

  const isPending = item.status === 'pending';

  return (
    <div>
      <PageHeader
        title={t('matching.match_approval_title', { id: item.id })}
        description={t('matching.match_submitted_date', { date: new Date(item.created_at).toLocaleDateString() })}
        actions={
          <Button
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            onPress={() => navigate(tenantPath('/admin/match-approvals'))}
          >
            {t('matching.back_to_approvals')}
          </Button>
        }
      />

      {/* Match Score Card */}
      <Card shadow="sm" className="mb-6">
        <CardHeader className="flex items-center gap-3 pb-0">
          <Shield size={20} className="text-primary" />
          <h3 className="text-lg font-semibold">{t('match_detail.match_information')}</h3>
          <div className="ml-auto">
            <StatusBadge status={item.status} />
          </div>
        </CardHeader>
        <CardBody>
          <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
            {/* Score */}
            <div className="flex flex-col items-center justify-center rounded-xl bg-default-50 p-6">
              <p className="mb-2 text-sm text-default-500">{t('match_detail.match_score')}</p>
              <div className="relative mb-2">
                <Progress
                  size="lg"
                  value={item.match_score}
                  color={scoreColor(item.match_score)}
                  className="w-32"
                  aria-label={`Match score: ${item.match_score}%`}
                />
              </div>
              <p className="text-3xl font-bold text-foreground">
                {Math.round(item.match_score)}%
              </p>
              <Chip
                size="sm"
                variant="flat"
                color={scoreColor(item.match_score)}
                className="mt-1"
              >
                {t(scoreLabelKey(item.match_score))}
              </Chip>
            </div>

            {/* Details */}
            <div className="space-y-3">
              <div>
                <p className="text-xs text-default-400">{t('match_detail.match_type')}</p>
                <Chip size="sm" variant="flat" className="mt-1 capitalize">
                  {(item.match_type || 'one_way').replace('_', ' ')}
                </Chip>
              </div>
              {item.distance_km !== null && item.distance_km !== undefined && (
                <div>
                  <p className="text-xs text-default-400">{t('match_detail.distance')}</p>
                  <p className="flex items-center gap-1 text-sm text-foreground">
                    <MapPin size={14} className="text-default-400" />
                    {item.distance_km.toFixed(1)} km
                  </p>
                </div>
              )}
              {item.category_name && (
                <div>
                  <p className="text-xs text-default-400">{t('match_detail.category')}</p>
                  <p className="text-sm text-foreground">{item.category_name}</p>
                </div>
              )}
            </div>

            {/* Match Reasons */}
            <div>
              <p className="mb-2 text-xs text-default-400">{t('match_detail.match_reasons')}</p>
              {item.match_reasons && item.match_reasons.length > 0 ? (
                <div className="flex flex-wrap gap-1.5">
                  {item.match_reasons.map((reason, i) => (
                    <Chip key={i} size="sm" variant="flat" color="primary">
                      {reason}
                    </Chip>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-default-400 italic">
                  No reasons recorded
                </p>
              )}
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Users */}
      <div className="mb-6 grid grid-cols-1 gap-6 md:grid-cols-2">
        {/* User 1 */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-3 pb-0">
            <User size={18} className="text-primary" />
            <h3 className="font-semibold">{t('match_detail.matched_user')}</h3>
          </CardHeader>
          <CardBody>
            <div className="flex items-start gap-4">
              <Avatar
                src={item.user_1_avatar || undefined}
                name={item.user_1_name}
                size="lg"
                className="shrink-0"
              />
              <div className="min-w-0 flex-1">
                <p className="text-lg font-semibold text-foreground">
                  {item.user_1_name}
                </p>
                {item.user_1_email && (
                  <p className="text-sm text-default-500">{item.user_1_email}</p>
                )}
                {item.user_1_location && (
                  <p className="mt-1 flex items-center gap-1 text-sm text-default-400">
                    <MapPin size={12} />
                    {item.user_1_location}
                  </p>
                )}
                {item.user_1_bio && (
                  <p className="mt-2 line-clamp-3 text-sm text-default-500">
                    {item.user_1_bio}
                  </p>
                )}
              </div>
            </div>
          </CardBody>
        </Card>

        {/* User 2 (Listing Owner) */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-3 pb-0">
            <User size={18} className="text-success" />
            <h3 className="font-semibold">{t('match_detail.listing_owner')}</h3>
          </CardHeader>
          <CardBody>
            <div className="flex items-start gap-4">
              <Avatar
                src={item.user_2_avatar || undefined}
                name={item.user_2_name}
                size="lg"
                className="shrink-0"
              />
              <div className="min-w-0 flex-1">
                <p className="text-lg font-semibold text-foreground">
                  {item.user_2_name}
                </p>
                {item.user_2_email && (
                  <p className="text-sm text-default-500">{item.user_2_email}</p>
                )}
                {item.user_2_location && (
                  <p className="mt-1 flex items-center gap-1 text-sm text-default-400">
                    <MapPin size={12} />
                    {item.user_2_location}
                  </p>
                )}
                {item.user_2_bio && (
                  <p className="mt-2 line-clamp-3 text-sm text-default-500">
                    {item.user_2_bio}
                  </p>
                )}
              </div>
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Listing Card */}
      {item.listing_title && (
        <Card shadow="sm" className="mb-6">
          <CardHeader className="flex items-center gap-3 pb-0">
            <FileText size={18} className="text-secondary" />
            <h3 className="font-semibold">{t('match_detail.associated_listing')}</h3>
          </CardHeader>
          <CardBody>
            <div className="space-y-2">
              <p className="text-lg font-medium text-foreground">
                {item.listing_title}
              </p>
              <div className="flex gap-2">
                {item.listing_type && (
                  <Chip size="sm" variant="flat" className="capitalize">
                    {item.listing_type}
                  </Chip>
                )}
                {item.listing_status && (
                  <StatusBadge status={item.listing_status} />
                )}
              </div>
              {item.listing_description && (
                <p className="line-clamp-3 text-sm text-default-500">
                  {item.listing_description}
                </p>
              )}
            </div>
          </CardBody>
        </Card>
      )}

      {/* Review section (if reviewed) */}
      {item.reviewed_at && (
        <Card shadow="sm" className="mb-6">
          <CardHeader className="flex items-center gap-3 pb-0">
            <Clock size={18} className="text-default-500" />
            <h3 className="font-semibold">{t('match_detail.review_details')}</h3>
          </CardHeader>
          <CardBody>
            <div className="space-y-2">
              <div className="flex items-center gap-3">
                <p className="text-sm text-default-400">{t('matching.reviewed_by')}</p>
                <p className="text-sm font-medium text-foreground">
                  {item.reviewer_name || t('matching.unknown')}
                </p>
              </div>
              <div className="flex items-center gap-3">
                <p className="text-sm text-default-400">{t('matching.reviewed_at')}</p>
                <p className="text-sm text-foreground">
                  {new Date(item.reviewed_at).toLocaleString()}
                </p>
              </div>
              {item.notes && (
                <>
                  <Divider className="my-2" />
                  <div>
                    <p className="mb-1 text-sm text-default-400">
                      {item.status === 'rejected' ? t('matching.rejection_reason_label') : t('matching.notes_label')}
                    </p>
                    <p className="rounded-lg bg-default-50 p-3 text-sm text-foreground">
                      {item.notes}
                    </p>
                  </div>
                </>
              )}
            </div>
          </CardBody>
        </Card>
      )}

      {/* Action buttons for pending items */}
      {isPending && (
        <Card shadow="sm">
          <CardBody className="flex flex-row items-center justify-end gap-3 p-4">
            <Button
              color="danger"
              variant="flat"
              startContent={<XCircle size={16} />}
              onPress={() => {
                setRejectModal(true);
                setRejectReason('');
              }}
            >
              {t('matching.reject_match')}
            </Button>
            <Button
              color="success"
              startContent={<CheckCircle size={16} />}
              onPress={handleApprove}
              isLoading={approveLoading}
            >
              {t('matching.approve_match')}
            </Button>
          </CardBody>
        </Card>
      )}

      {/* Reject modal */}
      <Modal
        isOpen={rejectModal}
        onClose={() => {
          setRejectModal(false);
          setRejectReason('');
        }}
        size="md"
      >
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <XCircle size={20} className="text-danger" />
            {t('matching.reject_match')}
          </ModalHeader>
          <ModalBody>
            <p className="mb-3 text-sm text-default-600">
              {t('matching.rejecting_match_between', {
                user1: item.user_1_name,
                user2: item.user_2_name,
              })}
            </p>
            <Textarea
              label={t('matching.label_rejection_reason')}
              placeholder={t('matching.placeholder_explain_why_this_match_is_being_rejected')}
              value={rejectReason}
              onValueChange={setRejectReason}
              variant="bordered"
              minRows={3}
              isRequired
            />
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onPress={() => {
                setRejectModal(false);
                setRejectReason('');
              }}
              isDisabled={rejectLoading}
            >
              {t('common.cancel')}
            </Button>
            <Button
              color="danger"
              onPress={handleReject}
              isLoading={rejectLoading}
              isDisabled={!rejectReason.trim()}
            >
              {t('matching.reject_match')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default MatchDetail;
