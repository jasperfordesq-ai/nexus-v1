// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button } from '@/components/ui/Button';
import { Chip } from '@/components/ui/Chip';
import { GlassCard } from '@/components/ui/GlassCard';
import { Input } from '@/components/ui/Input';
import { Modal, ModalContent, ModalHeader, ModalBody, ModalFooter } from '@/components/ui/Modal';
import { Select, SelectItem } from '@/components/ui/Select';
import { Spinner } from '@/components/ui/Spinner';
import { Switch } from '@/components/ui/Switch';
import { Textarea } from '@/components/ui/Textarea';
import { Tooltip } from '@/components/ui/Tooltip';
/**
 * Scheduled Post Panel
 * Admin panel for scheduling group posts (discussions/announcements).
 */

import { useState, useEffect, useCallback } from 'react';

import CalendarClock from 'lucide-react/icons/calendar-clock';
import Plus from 'lucide-react/icons/plus';
import X from 'lucide-react/icons/x';
import { useToast } from '@/contexts';
import { formatDateTime } from '@/lib/helpers';
import { useTranslation } from 'react-i18next';
import {
  cancelScheduledGroupPost,
  createScheduledGroupPost,
  GroupApiError,
  listScheduledGroupPosts,
  type CreateScheduledGroupPostInput,
  type ScheduledGroupPost,
  type ScheduledGroupPostRecurrence,
  type ScheduledGroupPostType,
} from '../api';

interface ScheduledPostPanelProps {
  groupId: number;
  isAdmin: boolean;
}

const POST_TYPES = ['discussion', 'announcement'] as const;
const RECURRENCE_PATTERNS = ['daily', 'weekly', 'monthly'] as const;

const getPostTypeKey = (type: string) => {
  if (type === 'discussion' || type === 'announcement') {
    return `scheduled.type_${type}`;
  }
  return 'scheduled.type_unknown';
};

const getRecurrencePatternKey = (pattern: string) => {
  if (pattern === 'daily' || pattern === 'weekly' || pattern === 'monthly') {
    return `scheduled.pattern_${pattern}`;
  }
  return 'scheduled.pattern_unknown';
};

const typeColorMap: Record<string, 'primary' | 'warning'> = {
  discussion: 'primary',
  announcement: 'warning',
};

export function ScheduledPostPanel({ groupId, isAdmin }: ScheduledPostPanelProps) {
  const { t } = useTranslation('groups');
  const toast = useToast();

  const [posts, setPosts] = useState<ScheduledGroupPost[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadFailed, setLoadFailed] = useState(false);
  const [modalOpen, setModalOpen] = useState(false);

  // Form state
  const [formType, setFormType] = useState<ScheduledGroupPostType>('discussion');
  const [formTitle, setFormTitle] = useState('');
  const [formContent, setFormContent] = useState('');
  const [formScheduledAt, setFormScheduledAt] = useState('');
  const [formRecurring, setFormRecurring] = useState(false);
  const [formPattern, setFormPattern] = useState<ScheduledGroupPostRecurrence>('weekly');
  const [creating, setCreating] = useState(false);

  const loadPosts = useCallback(async (signal?: AbortSignal) => {
    setLoading(true);
    setLoadFailed(false);
    try {
      setPosts(await listScheduledGroupPosts(groupId, { signal }));
    } catch (error) {
      if (error instanceof GroupApiError && error.isCancellation) return;
      setLoadFailed(true);
      toast.error(t('scheduled.load_failed'));
    } finally {
      if (!signal?.aborted) setLoading(false);
    }
  }, [groupId, toast, t]);

  useEffect(() => {
    if (!isAdmin) {
      setPosts([]);
      setLoadFailed(false);
      setLoading(false);
      return undefined;
    }

    const controller = new AbortController();
    void loadPosts(controller.signal);
    return () => controller.abort();
  }, [isAdmin, loadPosts]);

  const resetForm = () => {
    setFormType('discussion');
    setFormTitle('');
    setFormContent('');
    setFormScheduledAt('');
    setFormRecurring(false);
    setFormPattern('weekly');
  };

  const handleCreate = async () => {
    if (!formTitle.trim() || !formContent.trim() || !formScheduledAt) {
      toast.error(t('scheduled.fields_required'));
      return;
    }

    setCreating(true);
    try {
      const body: CreateScheduledGroupPostInput = {
        post_type: formType,
        title: formTitle.trim(),
        content: formContent.trim(),
        scheduled_at: formScheduledAt,
      };
      if (formRecurring) {
        body.is_recurring = true;
        body.recurrence_pattern = formPattern;
      }

      await createScheduledGroupPost(groupId, body);
      toast.success(t('scheduled.created'));
      setModalOpen(false);
      resetForm();
      await loadPosts();
    } catch {
      toast.error(t('scheduled.create_failed'));
    } finally {
      setCreating(false);
    }
  };

  const handleCancel = async (postId: number) => {
    try {
      await cancelScheduledGroupPost(groupId, postId);
      setPosts((prev) => prev.filter((p) => p.id !== postId));
      toast.success(t('scheduled.cancelled'));
    } catch {
      toast.error(t('scheduled.cancel_failed'));
    }
  };

  if (!isAdmin) return null;

  return (
    <GlassCard className="space-y-5 p-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <CalendarClock size={18} className="text-accent" />
          <h3 className="text-base font-semibold text-foreground">
            {t('scheduled.title')}
          </h3>
        </div>

        <Button
          size="sm"
          color="primary"
          variant="solid"
          className="shrink-0"
          startContent={<Plus size={14} />}
          onPress={() => setModalOpen(true)}
        >
          {t('scheduled.add')}
        </Button>
      </div>

      {loading ? (
        <div role="status" aria-busy="true" aria-label={t('loading', { ns: 'common' })} className="flex items-center justify-center py-8">
          <Spinner size="md" />
        </div>
      ) : loadFailed ? (
        <p role="alert" className="py-6 text-center text-sm text-danger">
          {t('scheduled.load_failed')}
        </p>
      ) : posts.length === 0 ? (
        <p className="text-sm text-muted text-center py-6">
          {t('scheduled.empty')}
        </p>
      ) : (
        <div className="space-y-3">
          {posts.map((post) => (
            <div
              key={post.id}
              className="flex items-start gap-3 rounded-lg border border-border bg-surface p-3 shadow-sm transition-colors hover:bg-surface-secondary dark:hover:bg-surface-secondary/5"
            >
              <div className="flex-1 min-w-0 space-y-1">
                <p className="text-sm font-medium text-foreground">{post.title}</p>
                <div className="flex flex-wrap items-center gap-1.5">
                  <Chip
                    size="sm"
                    variant="flat"
                    color={typeColorMap[post.post_type] ?? 'default'}
                  >
                    {t(getPostTypeKey(post.post_type))}
                  </Chip>
                  <span className="text-xs text-muted">
                    {formatDateTime(post.scheduled_at)}
                  </span>
                  {post.is_recurring && post.recurrence_pattern && (
                    <Chip size="sm" variant="flat" color="secondary">
                      {t(getRecurrencePatternKey(post.recurrence_pattern))}
                    </Chip>
                  )}
                </div>
              </div>

              <Tooltip content={t('scheduled.cancel_label')}>
                <Button
                  size="sm"
                  variant="flat"
                  color="danger"
                  isIconOnly
                  className="shrink-0"
                  onPress={() => handleCancel(post.id)}
                  aria-label={t('scheduled.cancel_label')}
                >
                  <X size={14} />
                </Button>
              </Tooltip>
            </div>
          ))}
        </div>
      )}

      {/* Schedule Post Modal */}
      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} size="lg">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <CalendarClock size={20} className="text-accent" />
            {t('scheduled.add_title')}
          </ModalHeader>

          <ModalBody>
            <div className="space-y-4">
              <Select
                label={t('scheduled.type_label')}
                selectedKeys={new Set([formType])}
                onSelectionChange={(keys) => {
                  const selected = Array.from(keys)[0];
                  if (typeof selected === 'string' && POST_TYPES.includes(selected as ScheduledGroupPostType)) {
                    setFormType(selected as ScheduledGroupPostType);
                  }
                }}
                variant="bordered"
              >
                {POST_TYPES.map((type) => (
                  <SelectItem key={type} id={type}>
                    {t(`scheduled.type_${type}`)}
                  </SelectItem>
                ))}
              </Select>

              <Input
                label={t('scheduled.title_label')}
                placeholder={t('scheduled.title_placeholder')}
                value={formTitle}
                onValueChange={setFormTitle}
                variant="bordered"
                isRequired
              />

              <Textarea
                label={t('scheduled.content_label')}
                placeholder={t('scheduled.content_placeholder')}
                value={formContent}
                onValueChange={setFormContent}
                minRows={3}
                maxRows={8}
                variant="bordered"
                isRequired
              />

              <Input
                label={t('scheduled.datetime_label')}
                type="datetime-local"
                value={formScheduledAt}
                onValueChange={setFormScheduledAt}
                variant="bordered"
                isRequired
              />

              <div className="space-y-3">
                <Switch
                  isSelected={formRecurring}
                  onValueChange={setFormRecurring}
                  size="sm"
                >
                  {t('scheduled.recurring_label')}
                </Switch>

                {formRecurring && (
                  <Select
                    label={t('scheduled.pattern_label')}
                    selectedKeys={new Set([formPattern])}
                    onSelectionChange={(keys) => {
                      const selected = Array.from(keys)[0];
                      if (
                        typeof selected === 'string'
                        && RECURRENCE_PATTERNS.includes(selected as ScheduledGroupPostRecurrence)
                      ) {
                        setFormPattern(selected as ScheduledGroupPostRecurrence);
                      }
                    }}
                    variant="bordered"
                    size="sm"
                  >
                    {RECURRENCE_PATTERNS.map((pattern) => (
                      <SelectItem key={pattern} id={pattern}>
                        {t(`scheduled.pattern_${pattern}`)}
                      </SelectItem>
                    ))}
                  </Select>
                )}
              </div>
            </div>
          </ModalBody>

          <ModalFooter>
            <Button variant="flat" onPress={() => setModalOpen(false)}>
              {t('common:cancel')}
            </Button>
            <Button
              color="primary"
              onPress={handleCreate}
              isLoading={creating}
            >
              {t('scheduled.create_btn')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </GlassCard>
  );
}

export default ScheduledPostPanel;
