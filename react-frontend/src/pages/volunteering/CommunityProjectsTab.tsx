// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CommunityProjectsTab - Browse and propose community volunteer projects
 */

import { useState, useEffect, useCallback } from 'react';
import { motion } from 'framer-motion';
import {
  Button,
  Chip,
  Input,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Textarea,
  useDisclosure,
} from '@heroui/react';
import {
  Heart,
  Plus,
  MapPin,
  Calendar,
  User,
  Users,
  AlertTriangle,
  Lightbulb,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

/* ─────────────────────────── Types ─────────────────────────── */
interface Project {
  id: number;
  title: string;
  description: string;
  category: string | null;
  location: string | null;
  target_volunteers: number | null;
  proposed_date: string | null;
  status: 'proposed' | 'under_review' | 'approved' | 'rejected' | 'active' | 'completed' | 'cancelled';
  supporter_count: number;
  has_supported: boolean;
  proposer_name: string;
  created_at: string;
}

type StatusColor = 'default' | 'warning' | 'success' | 'danger' | 'primary';
const STATUS_COLORS: Record<Project['status'], StatusColor> = {
  proposed: 'default',
  under_review: 'warning',
  approved: 'success',
  rejected: 'danger',
  active: 'primary',
  completed: 'success',
  cancelled: 'default',
};
const STATUS_LABELS: Record<Project['status'], string> = {
  proposed: 'Proposed',
  under_review: 'Under Review',
  approved: 'Approved',
  rejected: 'Rejected',
  active: 'Active',
  completed: 'Completed',
  cancelled: 'Cancelled',
};

/* ─────────────────────────── Component ─────────────────────────── */
export function CommunityProjectsTab() {
  const { t } = useTranslation('volunteering');
  const toast = useToast();
  const { isOpen, onOpen, onOpenChange } = useDisclosure();
  const [projects, setProjects] = useState<Project[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [togglingId, setTogglingId] = useState<number | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [form, setForm] = useState({
    title: '',
    description: '',
    category: '',
    location: '',
    target_volunteers: '',
    proposed_date: '',
  });
  const load = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);
      const res = await api.get<{ items: Project[]; cursor?: string; has_more: boolean }>(
        '/v2/volunteering/community-projects',
      );
      if (res.success && res.data) {
        const raw = res.data as Record<string, unknown>;
        const items = Array.isArray(raw.items)
          ? (raw.items as Project[])
          : Array.isArray(res.data)
            ? (res.data as unknown as Project[])
            : [];
        setProjects(items);
      } else {
        setError(t('community_projects.load_error', 'Unable to load community projects.'));
      }
    } catch (err) {
      logError('Failed to load community projects', err);
      setError(t('community_projects.load_error', 'Unable to load community projects.'));
    } finally {
      setIsLoading(false);
    }
  }, [t]);
  useEffect(() => {
    load();
  }, [load]);

  const toggleSupport = async (project: Project) => {
    try {
      setTogglingId(project.id);
      if (project.has_supported) {
        await api.delete(`/v2/volunteering/community-projects/${project.id}/support`);
        setProjects((prev) =>
          prev.map((p) =>
            p.id === project.id
              ? { ...p, has_supported: false, supporter_count: p.supporter_count - 1 }
              : p,
          ),
        );
      } else {
        await api.post(`/v2/volunteering/community-projects/${project.id}/support`, {});
        setProjects((prev) =>
          prev.map((p) =>
            p.id === project.id
              ? { ...p, has_supported: true, supporter_count: p.supporter_count + 1 }
              : p,
          ),
        );
      }
    } catch (err) {
      logError('Failed to toggle project support', err);
      toast.error(t('community_projects.support_failed', 'Failed to update support.'));
    } finally {
      setTogglingId(null);
    }
  };

  const handleSubmit = async (onClose: () => void) => {
    if (!form.title.trim() || !form.description.trim()) {
      toast.error(t('community_projects.title_required', 'Title and description are required.'));
      return;
    }
    try {
      setIsSubmitting(true);
      const payload: Record<string, unknown> = {
        title: form.title.trim(),
        description: form.description.trim(),
      };
      if (form.category.trim()) payload.category = form.category.trim();
      if (form.location.trim()) payload.location = form.location.trim();
      if (form.target_volunteers) payload.target_volunteers = Number(form.target_volunteers);
      if (form.proposed_date) payload.proposed_date = form.proposed_date;
      const res = await api.post('/v2/volunteering/community-projects', payload);
      if (res.success) {
        toast.success(t('community_projects.propose_success', 'Project proposed successfully!'));
        setForm({ title: '', description: '', category: '', location: '', target_volunteers: '', proposed_date: '' });
        onClose();
        load();
      } else {
        toast.error(t('community_projects.propose_error', 'Failed to propose project.'));
      }
    } catch (err) {
      logError('Failed to propose community project', err);
      toast.error(t('community_projects.propose_error', 'Failed to propose project.'));
    } finally {
      setIsSubmitting(false);
    }
  };

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.06 } },
  };
  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };
  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-2">
        <div className="flex items-center gap-2">
          <Lightbulb className="w-5 h-5 text-amber-400" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary">{t('community_projects.heading', 'Community Projects')}</h2>
        </div>
        <Button
          className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
          size="sm"
          startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
          onPress={onOpen}
        >
          {t('community_projects.propose', 'Propose a Project')}
        </Button>
      </div>

      {/* Error */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <p className="text-theme-muted mb-4">{error}</p>
          <Button className="bg-gradient-to-r from-rose-500 to-pink-600 text-white" onPress={load}>
            {t('community_projects.try_again', 'Try Again')}
          </Button>
        </GlassCard>
      )}

      {/* Loading */}
      {!error && isLoading && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {[1, 2, 3, 4].map((i) => (
            <GlassCard key={i} className="p-5 animate-pulse">
              <div className="h-5 bg-theme-hover rounded w-2/3 mb-3" />
              <div className="h-3 bg-theme-hover rounded w-full mb-2" />
              <div className="h-3 bg-theme-hover rounded w-1/2 mb-3" />
              <div className="h-3 bg-theme-hover rounded w-1/4" />
            </GlassCard>
          ))}
        </div>
      )}

      {/* Empty */}
      {!error && !isLoading && projects.length === 0 && (
        <EmptyState
          icon={<Lightbulb className="w-12 h-12" aria-hidden="true" />}
          title={t('community_projects.empty_title', 'No community projects yet')}
          description={t('community_projects.empty_description', 'Be the first to propose a volunteer project for the community!')}
        />
      )}

      {/* Project Grid */}
      {!error && !isLoading && projects.length > 0 && (
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="grid grid-cols-1 md:grid-cols-2 gap-4"
        >
          {projects.map((project) => (
            <motion.div key={project.id} variants={itemVariants}>
              <GlassCard className="p-5 flex flex-col h-full">
                {/* Title + Status */}
                <div className="flex items-start justify-between gap-2 mb-2">
                  <h3 className="text-base font-semibold text-theme-primary line-clamp-2">
                    {project.title}
                  </h3>
                  <Chip size="sm" variant="flat" color={STATUS_COLORS[project.status]}>
                    {t(`community_projects.status.${project.status}`, STATUS_LABELS[project.status])}
                  </Chip>
                </div>

                {/* Description */}
                <p className="text-sm text-theme-muted line-clamp-3 mb-3">{project.description}</p>

                {/* Meta */}
                <div className="flex flex-wrap items-center gap-2 text-xs text-theme-subtle mb-3">
                  <span className="flex items-center gap-1">
                    <User className="w-3 h-3" aria-hidden="true" />
                    {project.proposer_name}
                  </span>
                  {project.category && (
                    <Chip size="sm" variant="flat" className="bg-theme-elevated text-theme-muted">
                      {project.category}
                    </Chip>
                  )}
                  {project.location && (
                    <span className="flex items-center gap-1">
                      <MapPin className="w-3 h-3" aria-hidden="true" />
                      {project.location}
                    </span>
                  )}
                  {project.proposed_date && (
                    <span className="flex items-center gap-1">
                      <Calendar className="w-3 h-3" aria-hidden="true" />
                      {new Date(project.proposed_date).toLocaleDateString()}
                    </span>
                  )}
                  {project.target_volunteers != null && (
                    <span className="flex items-center gap-1">
                      <Users className="w-3 h-3" aria-hidden="true" />
                      {t('community_projects.volunteers_needed', '{{count}} needed', { count: project.target_volunteers })}
                    </span>
                  )}
                </div>

                {/* Support row */}
                <div className="mt-auto flex items-center justify-between pt-2 border-t border-theme-default">
                  <span className="text-xs text-theme-subtle">
                    {new Date(project.created_at).toLocaleDateString()}
                  </span>
                  <Button
                    size="sm"
                    variant={project.has_supported ? 'solid' : 'flat'}
                    className={
                      project.has_supported
                        ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white'
                        : 'bg-theme-elevated text-theme-muted'
                    }
                    startContent={<Heart className={`w-4 h-4 ${project.has_supported ? 'fill-current' : ''}`} aria-hidden="true" />}
                    isLoading={togglingId === project.id}
                    onPress={() => toggleSupport(project)}
                  >
                    {project.supporter_count}
                  </Button>
                </div>
              </GlassCard>
            </motion.div>
          ))}
        </motion.div>
      )}

      {/* Propose Modal */}
      <Modal
        isOpen={isOpen}
        onOpenChange={onOpenChange}
        size="lg"
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="text-theme-primary">{t('community_projects.modal_title', 'Propose a Project')}</ModalHeader>
              <ModalBody className="gap-4">
                <Input
                  label={t('community_projects.form.title', 'Title')}
                  placeholder={t('community_projects.form.title_placeholder', 'Project title')}
                  variant="bordered"
                  isRequired
                  value={form.title}
                  onValueChange={(v) => setForm((f) => ({ ...f, title: v }))}
                />
                <Textarea
                  label={t('community_projects.form.description', 'Description')}
                  placeholder={t('community_projects.form.description_placeholder', 'Describe the project and its goals')}
                  variant="bordered"
                  isRequired
                  minRows={3}
                  value={form.description}
                  onValueChange={(v) => setForm((f) => ({ ...f, description: v }))}
                />
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <Input
                    label={t('community_projects.form.category', 'Category')}
                    placeholder={t('community_projects.form.category_placeholder', 'e.g. Environment, Education')}
                    variant="bordered"
                    value={form.category}
                    onValueChange={(v) => setForm((f) => ({ ...f, category: v }))}
                  />
                  <Input
                    label={t('community_projects.form.location', 'Location')}
                    placeholder={t('community_projects.form.location_placeholder', 'Where will this take place?')}
                    variant="bordered"
                    value={form.location}
                    onValueChange={(v) => setForm((f) => ({ ...f, location: v }))}
                  />
                  <Input
                    label={t('community_projects.form.target_volunteers', 'Target Volunteers')}
                    placeholder={t('community_projects.form.target_volunteers_placeholder', 'Number of volunteers needed')}
                    type="number"
                    variant="bordered"
                    value={form.target_volunteers}
                    onValueChange={(v) => setForm((f) => ({ ...f, target_volunteers: v }))}
                  />
                  <Input
                    label={t('community_projects.form.proposed_date', 'Proposed Date')}
                    type="date"
                    variant="bordered"
                    value={form.proposed_date}
                    onValueChange={(v) => setForm((f) => ({ ...f, proposed_date: v }))}
                  />
                </div>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>{t('community_projects.cancel', 'Cancel')}</Button>
                <Button
                  className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
                  isLoading={isSubmitting}
                  onPress={() => handleSubmit(onClose)}
                >
                  {t('community_projects.propose_button', 'Propose')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default CommunityProjectsTab;
