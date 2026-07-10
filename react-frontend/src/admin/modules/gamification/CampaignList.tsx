// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Campaign List
 * Lists all gamification campaigns with status, actions, and delete confirmation.
 * Parity: PHP Admin\GamificationController@campaigns
 */

import { getFormattingLocale } from '@/lib/helpers';
import { useState, useCallback, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';import Plus from 'lucide-react/icons/plus';
import MoreVertical from 'lucide-react/icons/ellipsis-vertical';
import Edit from 'lucide-react/icons/square-pen';
import Trash2 from 'lucide-react/icons/trash-2';
import Megaphone from 'lucide-react/icons/megaphone';
import Play from 'lucide-react/icons/play';
import Pause from 'lucide-react/icons/pause';
import RotateCcw from 'lucide-react/icons/rotate-ccw';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useToast,
  useTenant } from '@/contexts';
import { adminGamification } from '../../api/adminApi';
import { DataTable, StatusBadge, type Column } from '../../components/DataTable';
import { PageHeader } from '../../components/PageHeader';
import { ConfirmModal } from '../../components/ConfirmModal';
import { EmptyState } from '../../components/EmptyState';
import type { Campaign } from '../../api/types';

import { Dropdown, DropdownTrigger, DropdownMenu, DropdownItem, Button } from '@/components/ui';
// ─────────────────────────────────────────────────────────────────────────────
// Actions menu per row
// ─────────────────────────────────────────────────────────────────────────────

interface CampaignActionsProps {
  campaign: Campaign;
  t: (key: string, options?: Record<string, unknown>) => string;
  navigate: (path: string) => void;
  tenantPath: (path: string) => string;
  handleStatusChange: (campaign: Campaign, newStatus: Campaign['status']) => Promise<void>;
  setDeleteTarget: React.Dispatch<React.SetStateAction<Campaign | null>>;
}

function CampaignActions({ campaign, t, navigate, tenantPath, handleStatusChange, setDeleteTarget }: CampaignActionsProps) {
  type ActionKey = 'edit' | 'activate' | 'pause' | 'resume' | 'delete';

  const handleAction = (key: React.Key) => {
    const action = key as ActionKey;
    if (action === 'edit') {
      navigate(tenantPath(`/admin/gamification/campaigns/edit/${campaign.id}`));
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
        <Button isIconOnly size="sm" variant="tertiary" aria-label={t('gamification.label_campaign_actions')}>
          <MoreVertical size={16} />
        </Button>
      </DropdownTrigger>
      <DropdownMenu aria-label={t('gamification.label_campaign_actions')} onAction={handleAction}>
        <DropdownItem key="edit" id="edit" startContent={<Edit size={14} />}>
          {t('gamification.edit')}
        </DropdownItem>
        <DropdownItem
          key="activate" id="activate"
          startContent={<Play size={14} />}
          color="success"
          className={campaign.status === 'draft' ? 'text-success' : 'hidden'}
        >
          {t('gamification.activate')}
        </DropdownItem>
        <DropdownItem
          key="pause" id="pause"
          startContent={<Pause size={14} />}
          color="warning"
          className={campaign.status === 'active' ? 'text-warning' : 'hidden'}
        >
          {t('gamification.pause')}
        </DropdownItem>
        <DropdownItem
          key="resume" id="resume"
          startContent={<RotateCcw size={14} />}
          color="success"
          className={campaign.status === 'paused' ? 'text-success' : 'hidden'}
        >
          {t('gamification.resume')}
        </DropdownItem>
        <DropdownItem key="delete" id="delete" startContent={<Trash2 size={14} />} className="text-danger" variant="danger">
          {t('gamification.delete')}
        </DropdownItem>
      </DropdownMenu>
    </Dropdown>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function CampaignList() {
  const { t } = useTranslation('admin_gamification');
  usePageTitle(t('gamification.page_title'));
  const toast = useToast();
  const { tenantPath } = useTenant();
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
      // res.data is already unwrapped by the API client — never double-unwrap
      setCampaigns(Array.isArray(res.data) ? res.data : []);
    } else {
      toast.error(t('gamification.failed_to_load_campaigns'));
    }
    setLoading(false);
  }, [t, toast])


  useEffect(() => {
    loadCampaigns();
  }, [loadCampaigns]);

  const handleDelete = async () => {
    if (!deleteTarget) return;
    setDeleting(true);

    const res = await adminGamification.deleteCampaign(deleteTarget.id);
    if (res.success) {
      toast.success(t('gamification.campaign_deleted'));
      setDeleteTarget(null);
      loadCampaigns();
    } else {
      toast.error(t('gamification.failed_to_delete_campaign'));
    }

    setDeleting(false);
  };

  const handleStatusChange = async (campaign: Campaign, newStatus: Campaign['status']) => {
    const res = await adminGamification.updateCampaign(campaign.id, { status: newStatus });
    if (res.success) {
      toast.success(t('gamification.campaign_status_changed'));
      loadCampaigns();
    } else {
      toast.error(t('gamification.failed_to_update_status'));
    }
  };

  const columns: Column<Campaign>[] = [
    {
      key: 'name',
      label: t('gamification.col_name'),
      sortable: true,
      render: (c) => (
        <span className="font-medium text-foreground">{c.name}</span>
      ),
    },
    {
      key: 'status',
      label: t('gamification.col_status'),
      sortable: true,
      render: (c) => <StatusBadge status={c.status} />,
    },
    {
      key: 'badge_name',
      label: t('gamification.col_badge'),
      render: (c) => (
        <span className="text-sm text-muted">{c.badge_name || c.badge_key || '—'}</span>
      ),
    },
    {
      key: 'target_audience',
      label: t('gamification.col_target'),
      render: (c) => (
        <span className="text-sm text-muted capitalize">
          {(c.target_audience || '').replace(/_/g, ' ')}
        </span>
      ),
    },
    {
      key: 'total_awards',
      label: t('gamification.col_awards'),
      sortable: true,
      render: (c) => (
        <span className="text-sm text-foreground">{c.total_awards ?? 0}</span>
      ),
    },
    {
      key: 'created_at',
      label: t('gamification.col_created'),
      sortable: true,
      render: (c) => (
        <span className="text-sm text-muted">
          {c.created_at ? new Date(c.created_at).toLocaleDateString(getFormattingLocale()) : '—'}
        </span>
      ),
    },
    {
      key: 'actions',
      label: t('gamification.col_actions'),
      render: (c) => (
        <CampaignActions
          campaign={c}
          t={t}
          navigate={navigate}
          tenantPath={tenantPath}
          handleStatusChange={handleStatusChange}
          setDeleteTarget={setDeleteTarget}
        />
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title={t('gamification.campaign_list_title')}
        description={t('gamification.campaign_list_desc')}
        actions={
          <Button as={Link} to={tenantPath("/admin/gamification/campaigns/create")} startContent={<Plus size={16} />}>
            {t('gamification.create_campaign')}
          </Button>
        }
      />

      {campaigns.length === 0 && !loading ? (
        <EmptyState
          icon={Megaphone}
          title={t('gamification.no_campaigns_yet')}
          description={t('gamification.desc_create_your_first_campaign_to_start_awar')}
          actionLabel={t('gamification.create_campaign')}
          onAction={() => navigate(tenantPath('/admin/gamification/campaigns/create'))}
        />
      ) : (
        <DataTable
          columns={columns}
          data={campaigns}
          isLoading={loading}
          searchPlaceholder={t('gamification.search_campaigns')}
          onRefresh={loadCampaigns}
          emptyContent={t('gamification.no_campaigns_match_search')}
        />
      )}

      {/* Delete Confirmation */}
      {deleteTarget && (
        <ConfirmModal
          isOpen={!!deleteTarget}
          onClose={() => setDeleteTarget(null)}
          onConfirm={handleDelete}
          title={t('gamification.delete_campaign')}
          message={t('gamification.confirm_delete_campaign')}
          confirmLabel={t('gamification.delete')}
          confirmColor="danger"
          isLoading={deleting}
        />
      )}
    </div>
  );
}

export default CampaignList;
