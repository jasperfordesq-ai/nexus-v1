/**
 * Admin Custom Badges
 * Grid view of custom badges with create and delete actions.
 * Parity: PHP Admin\CustomBadgeController@index
 */

import { useState, useCallback, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Card, CardBody, Button } from '@heroui/react';
import { Plus, Award, Trash2 } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminGamification } from '../../api/adminApi';
import { PageHeader, ConfirmModal, EmptyState } from '../../components';
import type { BadgeDefinition } from '../../api/types';

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function CustomBadges() {
  usePageTitle('Admin - Custom Badges');
  const toast = useToast();
  const navigate = useNavigate();

  const [badges, setBadges] = useState<BadgeDefinition[]>([]);
  const [loading, setLoading] = useState(true);

  // Delete confirmation
  const [deleteTarget, setDeleteTarget] = useState<BadgeDefinition | null>(null);
  const [deleting, setDeleting] = useState(false);

  const loadBadges = useCallback(async () => {
    setLoading(true);
    const res = await adminGamification.listBadges();
    if (res.success && res.data) {
      const data = res.data as unknown;
      let all: BadgeDefinition[] = [];
      if (Array.isArray(data)) {
        all = data;
      } else if (data && typeof data === 'object' && 'data' in data) {
        all = (data as { data: BadgeDefinition[] }).data || [];
      }
      // Show only custom badges on this page
      setBadges(all.filter((b) => b.type === 'custom'));
    } else {
      toast.error('Failed to load badges');
    }
    setLoading(false);
  }, [toast]);

  useEffect(() => {
    loadBadges();
  }, [loadBadges]);

  const handleDelete = async () => {
    if (!deleteTarget || deleteTarget.id === null) return;
    setDeleting(true);

    const res = await adminGamification.deleteBadge(deleteTarget.id);
    if (res.success) {
      toast.success(`Badge "${deleteTarget.name}" deleted`);
      setDeleteTarget(null);
      loadBadges();
    } else {
      toast.error('Failed to delete badge');
    }

    setDeleting(false);
  };

  return (
    <div>
      <PageHeader
        title="Custom Badges"
        description="Create and manage custom badges for your community"
        actions={
          <Link to="../custom-badges/create">
            <Button color="primary" startContent={<Plus size={16} />}>
              Create Badge
            </Button>
          </Link>
        }
      />

      {loading ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {[1, 2, 3].map((i) => (
            <Card key={i} shadow="sm">
              <CardBody className="p-4">
                <div className="animate-pulse space-y-3">
                  <div className="flex items-center gap-3">
                    <div className="h-12 w-12 rounded-xl bg-default-200" />
                    <div className="flex-1 space-y-2">
                      <div className="h-4 w-32 rounded bg-default-200" />
                      <div className="h-3 w-20 rounded bg-default-200" />
                    </div>
                  </div>
                  <div className="h-3 w-full rounded bg-default-100" />
                </div>
              </CardBody>
            </Card>
          ))}
        </div>
      ) : badges.length === 0 ? (
        <EmptyState
          icon={Award}
          title="No custom badges"
          description="Create your first custom badge to reward community members for special achievements."
          actionLabel="Create Badge"
          onAction={() => navigate('../custom-badges/create')}
        />
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {badges.map((badge) => (
            <Card key={badge.key} shadow="sm" className="group">
              <CardBody className="p-4">
                <div className="flex items-start gap-3">
                  <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-success/10 text-success">
                    <Award size={24} />
                  </div>
                  <div className="flex-1 min-w-0">
                    <h4 className="font-semibold text-foreground truncate">{badge.name}</h4>
                    <p className="text-xs text-default-500 mt-0.5">
                      {badge.awarded_count} user{badge.awarded_count !== 1 ? 's' : ''} awarded
                    </p>
                  </div>
                  <Button
                    isIconOnly
                    size="sm"
                    variant="light"
                    color="danger"
                    className="opacity-0 group-hover:opacity-100 transition-opacity"
                    onPress={() => setDeleteTarget(badge)}
                    aria-label={`Delete ${badge.name}`}
                  >
                    <Trash2 size={16} />
                  </Button>
                </div>
                {badge.description && (
                  <p className="mt-2 text-sm text-default-600 line-clamp-2">{badge.description}</p>
                )}
              </CardBody>
            </Card>
          ))}
        </div>
      )}

      {/* Delete Confirmation */}
      {deleteTarget && (
        <ConfirmModal
          isOpen={!!deleteTarget}
          onClose={() => setDeleteTarget(null)}
          onConfirm={handleDelete}
          title="Delete Badge"
          message={
            deleteTarget.awarded_count > 0
              ? `Are you sure you want to delete "${deleteTarget.name}"? This badge has been awarded to ${deleteTarget.awarded_count} user(s). Their badge records will be removed.`
              : `Are you sure you want to delete "${deleteTarget.name}"? This action cannot be undone.`
          }
          confirmLabel="Delete"
          confirmColor="danger"
          isLoading={deleting}
        />
      )}
    </div>
  );
}

export default CustomBadges;
