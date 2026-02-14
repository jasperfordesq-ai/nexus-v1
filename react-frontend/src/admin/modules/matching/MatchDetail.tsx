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
import { useToast } from '@/contexts';
import { adminMatching } from '../../api/adminApi';
import { PageHeader, StatusBadge } from '../../components';
import type { MatchApprovalDetail } from '../../api/types';

// Score color helper
function scoreColor(score: number): 'danger' | 'warning' | 'success' {
  if (score < 50) return 'danger';
  if (score < 75) return 'warning';
  return 'success';
}

// Score label
function scoreLabel(score: number): string {
  if (score >= 90) return 'Excellent';
  if (score >= 75) return 'Good';
  if (score >= 50) return 'Fair';
  return 'Low';
}

export function MatchDetail() {
  usePageTitle('Admin - Match Detail');
  const toast = useToast();
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
      setError(res.error || 'Failed to load match approval');
    }
    setLoading(false);
  }, [id]);

  useEffect(() => {
    loadItem();
  }, [loadItem]);

  const handleApprove = async () => {
    if (!item) return;
    setApproveLoading(true);
    const res = await adminMatching.approveMatch(item.id);
    if (res.success) {
      toast.success(`Match #${item.id} approved`);
      loadItem();
    } else {
      toast.error(res.error || 'Failed to approve match');
    }
    setApproveLoading(false);
  };

  const handleReject = async () => {
    if (!item) return;
    if (!rejectReason.trim()) {
      toast.error('Please provide a reason for rejection');
      return;
    }
    setRejectLoading(true);
    const res = await adminMatching.rejectMatch(item.id, rejectReason.trim());
    if (res.success) {
      toast.success(`Match #${item.id} rejected`);
      setRejectModal(false);
      setRejectReason('');
      loadItem();
    } else {
      toast.error(res.error || 'Failed to reject match');
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
          title="Match Detail"
          actions={
            <Button
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate('/admin/match-approvals')}
            >
              Back
            </Button>
          }
        />
        <Card shadow="sm">
          <CardBody className="flex flex-col items-center justify-center py-16">
            <XCircle size={40} className="mb-3 text-danger" />
            <p className="text-lg font-medium text-foreground">
              Match Not Found
            </p>
            <p className="mt-1 text-sm text-default-500">
              {error || 'The match approval could not be loaded.'}
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
        title={`Match Approval #${item.id}`}
        description={`Submitted ${new Date(item.created_at).toLocaleDateString()}`}
        actions={
          <Button
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            onPress={() => navigate('/admin/match-approvals')}
          >
            Back to Approvals
          </Button>
        }
      />

      {/* Match Score Card */}
      <Card shadow="sm" className="mb-6">
        <CardHeader className="flex items-center gap-3 pb-0">
          <Shield size={20} className="text-primary" />
          <h3 className="text-lg font-semibold">Match Information</h3>
          <div className="ml-auto">
            <StatusBadge status={item.status} />
          </div>
        </CardHeader>
        <CardBody>
          <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
            {/* Score */}
            <div className="flex flex-col items-center justify-center rounded-xl bg-default-50 p-6">
              <p className="mb-2 text-sm text-default-500">Match Score</p>
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
                {scoreLabel(item.match_score)}
              </Chip>
            </div>

            {/* Details */}
            <div className="space-y-3">
              <div>
                <p className="text-xs text-default-400">Match Type</p>
                <Chip size="sm" variant="flat" className="mt-1 capitalize">
                  {(item.match_type || 'one_way').replace('_', ' ')}
                </Chip>
              </div>
              {item.distance_km !== null && item.distance_km !== undefined && (
                <div>
                  <p className="text-xs text-default-400">Distance</p>
                  <p className="flex items-center gap-1 text-sm text-foreground">
                    <MapPin size={14} className="text-default-400" />
                    {item.distance_km.toFixed(1)} km
                  </p>
                </div>
              )}
              {item.category_name && (
                <div>
                  <p className="text-xs text-default-400">Category</p>
                  <p className="text-sm text-foreground">{item.category_name}</p>
                </div>
              )}
            </div>

            {/* Match Reasons */}
            <div>
              <p className="mb-2 text-xs text-default-400">Match Reasons</p>
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
            <h3 className="font-semibold">Matched User</h3>
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
            <h3 className="font-semibold">Listing Owner</h3>
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
            <h3 className="font-semibold">Associated Listing</h3>
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
            <h3 className="font-semibold">Review Details</h3>
          </CardHeader>
          <CardBody>
            <div className="space-y-2">
              <div className="flex items-center gap-3">
                <p className="text-sm text-default-400">Reviewed by:</p>
                <p className="text-sm font-medium text-foreground">
                  {item.reviewer_name || 'Unknown'}
                </p>
              </div>
              <div className="flex items-center gap-3">
                <p className="text-sm text-default-400">Reviewed at:</p>
                <p className="text-sm text-foreground">
                  {new Date(item.reviewed_at).toLocaleString()}
                </p>
              </div>
              {item.notes && (
                <>
                  <Divider className="my-2" />
                  <div>
                    <p className="mb-1 text-sm text-default-400">
                      {item.status === 'rejected' ? 'Rejection reason:' : 'Notes:'}
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
              Reject Match
            </Button>
            <Button
              color="success"
              startContent={<CheckCircle size={16} />}
              onPress={handleApprove}
              isLoading={approveLoading}
            >
              Approve Match
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
            Reject Match
          </ModalHeader>
          <ModalBody>
            <p className="mb-3 text-sm text-default-600">
              Rejecting match between{' '}
              <strong>{item.user_1_name}</strong> and{' '}
              <strong>{item.user_2_name}</strong>.
              The user will be notified with your reason.
            </p>
            <Textarea
              label="Rejection reason"
              placeholder="Explain why this match is being rejected..."
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
              Cancel
            </Button>
            <Button
              color="danger"
              onPress={handleReject}
              isLoading={rejectLoading}
              isDisabled={!rejectReason.trim()}
            >
              Reject Match
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default MatchDetail;
