// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Campaign List
 * Lists all gamification campaigns with status, actions, and delete confirmation.
 * Parity: PHP Admin\GamificationController@campaigns
 */

import { useState, useCallback, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  Button,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
} from '@heroui/react';
import { Plus, MoreVertical, Edit, Trash2, Megaphone, Play, Pause, RotateCcw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminGamification } from '../../api/adminApi';
import { DataTable, PageHeader, ConfirmModal, StatusBadge, EmptyState, type Column } from '../../components';
import type { Campaign } from '../../api/types';

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function CampaignList() {
  usePageTitle('Admin - Campaigns');
  const toast = useToast();
  const navigate = useNavigate();

  const [campaigns, setCampaigns] = useState<Campaign[]>([]);
  const [loading, setLoading] = useState(true);

  // Delete confirmation
  const [deleteTarget, setDeleteTarget] = useState<Campaign | null>(null);
  const [deleting, setDeleting] = useState(false);

  const loadCampaigns = useCallback(async () => {
    setLoading(true);
    const res = await adminGamification.listCampaigns();
    if (res.success && res.data) {
      const data = res.data as unknown;
      if (Array.isArray(data)) {
        setCampaigns(data);
      } else if (data && typeof data === 'object' && 'data' in data) {
        setCampaigns((data as { data: Campaign[] }).data || []);
      }
    } else {
      toast.error('Failed to load campaigns');
    }
    setLoading(false);
  }, [toast]);

  useEffect(() => {
    loadCampaigns();
  }, [loadCampaigns]);

  const handleDelete = async () => {
    if (!deleteTarget) return;
    setDeleting(true);

    const res = await adminGamification.deleteCampaign(deleteTarget.id);
    if (res.success) {
      toast.success(`Campaign "${deleteTarget.name}" deleted`);
      setDeleteTarget(null);
      loadCampaigns();
    } else {
      toast.error('Failed to delete campaign');
    }

    setDeleting(false);
  };

  const handleStatusChange = async (campaign: Campaign, newStatus: Campaign['status']) => {
    const res = await adminGamification.updateCampaign(campaign.id, { status: newStatus });
    if (res.success) {
      toast.success(`Campaign "${campaign.name}" ${newStatus === 'active' ? 'activated' : newStatus === 'paused' ? 'paused' : 'updated'}`);
      loadCampaigns();
    } else {
      toast.error(`Failed to update campaign status`);
    }
  };

  // Actions menu per row
  function CampaignActions({ campaign }: { campaign: Campaign }) {
    type ActionKey = 'edit' | 'activate' | 'pause' | 'resume' | 'delete';

    const handleAction = (key: React.Key) => {
      const action = key as ActionKey;
      if (action === 'edit') {
        navigate(`/admin/gamification/campaigns/edit/${campaign.id}`);
      } else if (action === 'activate') {
        handleStatusChange(campaign, 'active');
      } else if (action === 'pause') {
        handleStatusChange(campaign, 'paused');
      } else if (action === 'resume') {
        handleStatusChange(campaign, 'active');
      } else if (action === 'delete') {
        setDeleteTarget(campaign);
      }
    };

    return (
      <Dropdown>
        <DropdownTrigger>
          <Button isIconOnly size="sm" variant="light">
            <MoreVertical size={16} />
          </Button>
        </DropdownTrigger>
        <DropdownMenu aria-label="Campaign actions" onAction={handleAction}>
          <DropdownItem key="edit" startContent={<Edit size={14} />}>
            Edit
          </DropdownItem>
          <DropdownItem
            key="activate"
            startContent={<Play size={14} />}
            color="success"
            className={campaign.status === 'draft' ? 'text-success' : 'hidden'}
          >
            Activate
          </DropdownItem>
          <DropdownItem
            key="pause"
            startContent={<Pause size={14} />}
            color="warning"
            className={campaign.status === 'active' ? 'text-warning' : 'hidden'}
          >
            Pause
          </DropdownItem>
          <DropdownItem
            key="resume"
            startContent={<RotateCcw size={14} />}
            color="success"
            className={campaign.status === 'paused' ? 'text-success' : 'hidden'}
          >
            Resume
          </DropdownItem>
          <DropdownItem key="delete" startContent={<Trash2 size={14} />} className="text-danger" color="danger">
            Delete
          </DropdownItem>
        </DropdownMenu>
      </Dropdown>
    );
  }

  const columns: Column<Campaign>[] = [
    {
      key: 'name',
      label: 'Name',
      sortable: true,
      render: (c) => (
        <span className="font-medium text-foreground">{c.name}</span>
      ),
    },
    {
      key: 'status',
      label: 'Status',
      sortable: true,
      render: (c) => <StatusBadge status={c.status} />,
    },
    {
      key: 'badge_name',
      label: 'Badge',
      render: (c) => (
        <span className="text-sm text-default-600">{c.badge_name || c.badge_key || '—'}</span>
      ),
    },
    {
      key: 'target_audience',
      label: 'Target',
      render: (c) => (
        <span className="text-sm text-default-600 capitalize">
          {(c.target_audience || '').replace(/_/g, ' ')}
        </span>
      ),
    },
    {
      key: 'total_awards',
      label: 'Awards',
      sortable: true,
      render: (c) => (
        <span className="text-sm text-foreground">{c.total_awards ?? 0}</span>
      ),
    },
    {
      key: 'created_at',
      label: 'Created',
      sortable: true,
      render: (c) => (
        <span className="text-sm text-default-500">
          {c.created_at ? new Date(c.created_at).toLocaleDateString() : '—'}
        </span>
      ),
    },
    {
      key: 'actions',
      label: 'Actions',
      render: (c) => <CampaignActions campaign={c} />,
    },
  ];

  return (
    <div>
      <PageHeader
        title="Campaigns"
        description="Manage gamification campaigns for badge and XP distribution"
        actions={
          <Link to="/admin/gamification/campaigns/create">
            <Button color="primary" startContent={<Plus size={16} />}>
              Create Campaign
            </Button>
          </Link>
        }
      />

      {campaigns.length === 0 && !loading ? (
        <EmptyState
          icon={Megaphone}
          title="No campaigns yet"
          description="Create your first campaign to start awarding badges and XP to users."
          actionLabel="Create Campaign"
          onAction={() => navigate('/admin/gamification/campaigns/create')}
        />
      ) : (
        <DataTable
          columns={columns}
          data={campaigns}
          isLoading={loading}
          searchPlaceholder="Search campaigns..."
          onRefresh={loadCampaigns}
          emptyContent="No campaigns match your search"
        />
      )}

      {/* Delete Confirmation */}
      {deleteTarget && (
        <ConfirmModal
          isOpen={!!deleteTarget}
          onClose={() => setDeleteTarget(null)}
          onConfirm={handleDelete}
          title="Delete Campaign"
          message={`Are you sure you want to delete "${deleteTarget.name}"? This action cannot be undone.`}
          confirmLabel="Delete"
          confirmColor="danger"
          isLoading={deleting}
        />
      )}
    </div>
  );
}

export default CampaignList;
