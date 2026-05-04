// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Volunteer Community Projects
 * Admin page to review and manage community-proposed volunteer projects.
 */

import { useState, useCallback, useEffect, useMemo } from 'react';
import {
  Button,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Textarea,
  Select,
  SelectItem,
  useDisclosure,
} from '@heroui/react';
import FolderKanban from 'lucide-react/icons/folder-kanban';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import ClipboardCheck from 'lucide-react/icons/clipboard-check';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import Play from 'lucide-react/icons/play';
import Flag from 'lucide-react/icons/flag';
import Users from 'lucide-react/icons/users';
import PlusCircle from 'lucide-react/icons/circle-plus';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminVolunteering } from '../../api/adminApi';
import { DataTable, PageHeader, StatCard, EmptyState, type Column } from '../../components';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

interface CommunityProject {
  id: number;
  title: string;
  proposer_name: string;
  category: string;
  target_volunteers: number;
  status: string;
  supporters_count: number;
  created_at: string;
}

const statusColorMap: Record<string, 'success' | 'warning' | 'danger' | 'default' | 'primary' | 'secondary'> = {
  proposed: 'default',
  under_review: 'warning',
  approved: 'success',
  rejected: 'danger',
  active: 'primary',
  completed: 'secondary',
  cancelled: 'danger',
};

const reviewStatuses = [
  { key: 'approved', label: 'Approved' },
  { key: 'rejected', label: 'Rejected' },
  { key: 'under_review', label: 'Request Changes' },
];

export default function VolunteerProjects() {
  const { t } = useTranslation('admin');
  usePageTitle(t('volunteering.projects_title', 'Community Projects'));
  const toast = useToast();
  const navigate = useNavigate();

  const [projects, setProjects] = useState<CommunityProject[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [reviewingProject, setReviewingProject] = useState<CommunityProject | null>(null);
  const [reviewStatus, setReviewStatus] = useState('');
  const [reviewNotes, setReviewNotes] = useState('');

  const { isOpen, onOpen, onClose } = useDisclosure();

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminVolunteering.getCommunityProjects();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setProjects(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setProjects((payload as { data: CommunityProject[] }).data || []);
        }
      }
    } catch {
      toast.error(t('volunteering.failed_to_load_projects', 'Failed to load community projects'));
      setProjects([]);
    }
    setLoading(false);
  }, [toast]);


  useEffect(() => { loadData(); }, [loadData]);

  const openReview = (project: CommunityProject) => {
    setReviewingProject(project);
    setReviewStatus('');
    setReviewNotes('');
    onOpen();
  };

  const handleReview = async () => {
    if (!reviewingProject || !reviewStatus) {
      toast.error(t('volunteering.select_status', 'Please select a review status'));
      return;
    }
    setSaving(true);
    try {
      await adminVolunteering.reviewCommunityProject(reviewingProject.id, {
        status: reviewStatus,
        review_notes: reviewNotes.trim() || undefined,
      });
      toast.success(t('volunteering.project_reviewed', 'Project review submitted'));
      onClose();
      loadData();
    } catch {
      toast.error(t('volunteering.review_failed', 'Failed to submit review'));
    }
    setSaving(false);
  };

  const stats = useMemo(() => {
    const total = projects.length;
    const approved = projects.filter((p) => p.status === 'approved').length;
    const active = projects.filter((p) => p.status === 'active').length;
    const completed = projects.filter((p) => p.status === 'completed').length;
    const totalSupporters = projects.reduce((sum, p) => sum + (p.supporters_count || 0), 0);
    return { total, approved, active, completed, totalSupporters };
  }, [projects]);

  const handleCreateOpportunity = (project: CommunityProject) => {
    const params = new URLSearchParams({
      from_project: String(project.id),
      title: project.title,
    });
    navigate(`/volunteering/create?${params.toString()}`);
  };

  const columns: Column<CommunityProject>[] = [
    {
      key: 'title',
      label: t('volunteering.col_title', 'Title'),
      sortable: true,
    },
    {
      key: 'proposer_name',
      label: t('volunteering.col_proposer', 'Proposer'),
      sortable: true,
    },
    {
      key: 'category',
      label: t('volunteering.col_category', 'Category'),
      sortable: true,
    },
    {
      key: 'target_volunteers',
      label: t('volunteering.col_target_volunteers', 'Target Volunteers'),
      sortable: true,
    },
    {
      key: 'status',
      label: t('volunteering.col_status', 'Status'),
      sortable: true,
      render: (row) => (
        <Chip size="sm" color={statusColorMap[row.status] || 'default'} variant="flat">
          {t(`volunteering.project_status_${row.status}`, row.status.replace(/_/g, ' '))}
        </Chip>
      ),
    },
    {
      key: 'supporters_count',
      label: t('volunteering.col_supporters', 'Supporters'),
      sortable: true,
    },
    {
      key: 'created_at',
      label: t('volunteering.col_date', 'Date'),
      sortable: true,
      render: (row) => (
        <span>{row.created_at ? new Date(row.created_at).toLocaleDateString() : '-'}</span>
      ),
    },
    {
      key: 'actions' as keyof CommunityProject,
      label: t('common.actions', 'Actions'),
      render: (row) => (
        <div className="flex items-center gap-1">
          <Button
            size="sm"
            variant="flat"
            color="primary"
            startContent={<ClipboardCheck size={14} />}
            onPress={() => openReview(row)}
          >
            {t('volunteering.review', 'Review')}
          </Button>
          {row.status === 'approved' && (
            <Button
              size="sm"
              variant="flat"
              color="success"
              startContent={<PlusCircle size={14} />}
              onPress={() => handleCreateOpportunity(row)}
            >
              {t('volunteering.create_opportunity', 'Create Opportunity')}
            </Button>
          )}
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title={t('volunteering.projects_title', 'Community Projects')}
        description={t('volunteering.projects_desc', 'Review and manage community-proposed volunteer projects')}
        actions={
          <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>
            {t('common.refresh', 'Refresh')}
          </Button>
        }
      />

      {/* Project Analytics Stats */}
      {projects.length > 0 && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5 mb-6">
          <StatCard
            label={t('volunteering.total_projects', 'Total Projects')}
            value={stats.total}
            icon={FolderKanban}
            color="default"
            loading={loading}
          />
          <StatCard
            label={t('volunteering.approved_projects', 'Approved')}
            value={stats.approved}
            icon={CheckCircle}
            color="success"
            loading={loading}
          />
          <StatCard
            label={t('volunteering.active_projects', 'Active')}
            value={stats.active}
            icon={Play}
            color="primary"
            loading={loading}
          />
          <StatCard
            label={t('volunteering.completed_projects', 'Completed')}
            value={stats.completed}
            icon={Flag}
            color="secondary"
            loading={loading}
          />
          <StatCard
            label={t('volunteering.total_supporters', 'Total Supporters')}
            value={stats.totalSupporters}
            icon={Users}
            color="warning"
            loading={loading}
          />
        </div>
      )}

      {projects.length === 0 && !loading ? (
        <EmptyState
          icon={FolderKanban}
          title={t('volunteering.no_projects', 'No community projects')}
          description={t('volunteering.no_projects_desc', 'No community projects have been proposed yet.')}
        />
      ) : (
        <DataTable columns={columns} data={projects} isLoading={loading} />
      )}

      {/* Review Modal */}
      <Modal isOpen={isOpen} onClose={onClose} size="lg">
        <ModalContent>
          <ModalHeader>
            {t('volunteering.review_project', 'Review Project')}: {reviewingProject?.title}
          </ModalHeader>
          <ModalBody>
            <div className="flex flex-col gap-4">
              <Select
                label={t('volunteering.review_decision', 'Decision')}
                selectedKeys={reviewStatus ? [reviewStatus] : []}
                onSelectionChange={(keys) => {
                  const selected = Array.from(keys)[0];
                  if (typeof selected === 'string') setReviewStatus(selected);
                }}
                variant="bordered"
                isRequired
              >
                {reviewStatuses.map((s) => (
                  <SelectItem key={s.key}>
                    {t(`volunteering.review_status_${s.key}`, s.label)}
                  </SelectItem>
                ))}
              </Select>
              <Textarea
                label={t('volunteering.review_notes', 'Review Notes')}
                value={reviewNotes}
                onValueChange={setReviewNotes}
                variant="bordered"
                placeholder={t('volunteering.review_notes_placeholder', 'Optional notes about your decision...')}
                minRows={3}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose}>{t('common.cancel', 'Cancel')}</Button>
            <Button color="primary" onPress={handleReview} isLoading={saving}>
              {t('volunteering.submit_review', 'Submit Review')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
