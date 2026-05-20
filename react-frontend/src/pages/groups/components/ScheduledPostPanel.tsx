// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Scheduled Post Panel
 * Admin panel for scheduling group posts (discussions/announcements).
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Button,
  Chip,
  Input,
  Select,
  SelectItem,
  Switch,
  Textarea,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Spinner,
  Tooltip,
} from '@heroui/react';
import CalendarClock from 'lucide-react/icons/calendar-clock';
import Plus from 'lucide-react/icons/plus';
import X from 'lucide-react/icons/x';
import { api } from '@/lib/api';
import { useToast } from '@/contexts';
import { GlassCard } from '@/components/ui';
import { formatDateTime } from '@/lib/helpers';
import { useTranslation } from 'react-i18next';

interface ScheduledPostPanelProps {
  groupId: number;
  isAdmin: boolean;
}

interface ScheduledPost {
  id: number;
  post_type: string;
  title: string;
  content: string;
  scheduled_at: string;
  is_recurring: boolean;
  recurrence_pattern: string | null;
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

export function ScheduledPostPanel({ groupId, isAdmin }: ScheduledPostPanelProps) {
  const { t } = useTranslation('groups');
  const toast = useToast();

  const [posts, setPosts] = useState<ScheduledPost[]>([]);
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);

  // Form state
  const [formType, setFormType] = useState<string>('discussion');
  const [formTitle, setFormTitle] = useState('');
  const [formContent, setFormContent] = useState('');
  const [formScheduledAt, setFormScheduledAt] = useState('');
  const [formRecurring, setFormRecurring] = useState(false);
  const [formPattern, setFormPattern] = useState<string>('weekly');
  const [creating, setCreating] = useState(false);

  const loadPosts = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get(`/v2/groups/${groupId}/scheduled-posts`);
      if (res.success && res.data) {
        const payload = res.data;
        setPosts(Array.isArray(payload) ? payload : []);
      }
    } catch {
      toast.error(t('scheduled.load_failed'));
    } finally {
      setLoading(false);
    }
  }, [groupId, toast, t]);

  useEffect(() => {
    loadPosts();
  }, [loadPosts]);

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
      const body: Record<string, unknown> = {
        post_type: formType,
        title: formTitle.trim(),
        content: formContent.trim(),
        scheduled_at: formScheduledAt,
      };
      if (formRecurring) {
        body.is_recurring = true;
        body.recurrence_pattern = formPattern;
      }

      const res = await api.post(`/v2/groups/${groupId}/scheduled-posts`, body);
      if (res.success) {
        toast.success(t('scheduled.created'));
        setModalOpen(false);
        resetForm();
        await loadPosts();
      } else {
        toast.error(t('scheduled.create_failed'));
      }
    } catch {
      toast.error(t('scheduled.create_failed'));
    } finally {
      setCreating(false);
    }
  };

  const handleCancel = async (postId: number) => {
    try {
      const res = await api.delete(`/v2/groups/${groupId}/scheduled-posts/${postId}`);
      if (res.success) {
        setPosts((prev) => prev.filter((p) => p.id !== postId));
        toast.success(t('scheduled.cancelled'));
      } else {
        toast.error(t('scheduled.cancel_failed'));
      }
    } catch {
      toast.error(t('scheduled.cancel_failed'));
    }
  };

  const typeColorMap: Record<string, 'primary' | 'warning'> = {
    discussion: 'primary',
    announcement: 'warning',
  };

  if (!isAdmin) return null;

  return (
    <GlassCard className="space-y-5 p-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <CalendarClock size={18} className="text-primary" />
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
        <div className="flex items-center justify-center py-8">
          <Spinner size="md" />
        </div>
      ) : posts.length === 0 ? (
        <p className="text-sm text-default-400 text-center py-6">
          {t('scheduled.empty')}
        </p>
      ) : (
        <div className="space-y-3">
          {posts.map((post) => (
            <div
              key={post.id}
              className="flex items-start gap-3 rounded-lg border border-default-200 bg-content1 p-3 shadow-sm transition-colors hover:bg-default-50 dark:hover:bg-default-100/5"
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
                  <span className="text-xs text-default-400">
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
            <CalendarClock size={20} className="text-primary" />
            {t('scheduled.add_title')}
          </ModalHeader>

          <ModalBody>
            <div className="space-y-4">
              <Select
                label={t('scheduled.type_label')}
                selectedKeys={new Set([formType])}
                onSelectionChange={(keys) => {
                  const selected = Array.from(keys)[0];
                  if (typeof selected === 'string') setFormType(selected);
                }}
                variant="bordered"
              >
                {POST_TYPES.map((type) => (
                  <SelectItem key={type}>
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
                      if (typeof selected === 'string') setFormPattern(selected);
                    }}
                    variant="bordered"
                    size="sm"
                  >
                    {RECURRENCE_PATTERNS.map((pattern) => (
                      <SelectItem key={pattern}>
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
