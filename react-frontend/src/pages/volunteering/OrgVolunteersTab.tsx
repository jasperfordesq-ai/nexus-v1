// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback, useRef } from 'react';
import { Avatar, Button, Spinner } from '@heroui/react';
import Users from 'lucide-react/icons/users';
import ChevronDown from 'lucide-react/icons/chevron-down';
import { GlassCard } from '@/components/ui';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import { useTranslation } from 'react-i18next';

interface Volunteer {
  id: number;
  name: string;
  avatar_url: string | null;
  email: string;
  total_hours: number;
  applications_count: number;
  applied_at: string;
}

interface VolunteersResponse {
  items: Volunteer[];
  cursor: string | null;
  has_more: boolean;
}

interface OrgVolunteersTabProps {
  orgId: number;
}

export default function OrgVolunteersTab({ orgId }: OrgVolunteersTabProps) {
  const { t } = useTranslation('volunteering');
  const toast = useToast();
  const [volunteers, setVolunteers] = useState<Volunteer[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [hasMore, setHasMore] = useState(false);
  const cursorRef = useRef<string | null>(null);
  const abortRef = useRef<AbortController | null>(null);
  const toastRef = useRef(toast);
  toastRef.current = toast;

  const loadVolunteers = useCallback(async (append = false) => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    try {
      if (append) setIsLoadingMore(true);
      else setIsLoading(true);

      const params = new URLSearchParams({ per_page: '20' });
      if (append && cursorRef.current) params.set('cursor', cursorRef.current);

      const response = await api.get<VolunteersResponse>(
        `/v2/volunteering/organisations/${orgId}/volunteers?${params}`
      );

      if (controller.signal.aborted) return;

      if (response.success && response.data) {
        // api.get() already unwraps { data: [...], meta: {...} } → response.data = [...], response.meta = {...}
        const items = Array.isArray(response.data) ? response.data : [];
        const cursor = response.meta?.cursor ?? null;
        const has_more = response.meta?.has_more ?? false;
        if (append) {
          setVolunteers((prev) => [...prev, ...items]);
        } else {
          setVolunteers(items);
        }
        cursorRef.current = cursor;
        setHasMore(has_more);
      } else {
        toastRef.current.error(t('org_volunteers.load_failed', 'Failed to load volunteers.'));
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load volunteers', err);
      toastRef.current.error(t('org_volunteers.load_failed', 'Failed to load volunteers.'));
    } finally {
      if (!controller.signal.aborted) {
        setIsLoading(false);
        setIsLoadingMore(false);
      }
    }
  }, [orgId, t]);

  useEffect(() => {
    cursorRef.current = null;
    loadVolunteers();
    return () => { abortRef.current?.abort(); };
  }, [loadVolunteers]);

  if (isLoading) {
    return (
      <div className="flex justify-center py-16">
        <Spinner size="lg" />
      </div>
    );
  }

  if (volunteers.length === 0) {
    return (
      <GlassCard className="p-8 text-center">
        <div className="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-100 to-cyan-100 dark:from-blue-900/30 dark:to-cyan-900/30 flex items-center justify-center mx-auto mb-4">
          <Users className="w-7 h-7 text-[var(--color-info)]" aria-hidden="true" />
        </div>
        <p className="text-theme-muted">{t('org_volunteers.none', 'No approved volunteers yet.')}</p>
        <p className="text-sm text-theme-subtle mt-1">{t('org_volunteers.none_desc', 'Volunteers will appear here after their applications are approved.')}</p>
      </GlassCard>
    );
  }

  return (
    <div className="space-y-4">
      <GlassCard className="p-6">
        <div className="flex items-center gap-3 mb-4">
          <h2 className="text-lg font-semibold text-theme-primary flex items-center gap-2">
            <Users className="w-5 h-5 text-blue-400" aria-hidden="true" />
            {t('org_volunteers.heading', 'Volunteer Roster')}
          </h2>
          <span className="text-sm text-theme-muted">({volunteers.length}{hasMore ? '+' : ''})</span>
        </div>

        <div className="space-y-3">
          {volunteers.map((vol) => (
            <div
              key={vol.id}
              className="flex flex-col sm:flex-row sm:items-center gap-3 p-4 rounded-xl bg-theme-elevated border border-theme-default"
            >
              <Avatar
                src={resolveAvatarUrl(vol.avatar_url) || undefined}
                name={vol.name}
                size="md"
                className="flex-shrink-0"
              />
              <div className="flex-1 min-w-0">
                <p className="font-medium text-theme-primary text-sm">{vol.name}</p>
                <p className="text-xs text-theme-subtle">{vol.email}</p>
              </div>
              <div className="flex items-center gap-4 text-sm">
                <div className="text-center">
                  <p className="font-semibold text-theme-primary">{vol.total_hours}h</p>
                  <p className="text-xs text-theme-subtle">{t('org_volunteers.hours', 'Hours')}</p>
                </div>
                <div className="text-center">
                  <p className="font-semibold text-theme-primary">{vol.applications_count}</p>
                  <p className="text-xs text-theme-subtle">{t('org_volunteers.roles', 'Roles')}</p>
                </div>
              </div>
            </div>
          ))}
        </div>

        {hasMore && (
          <div className="flex justify-center pt-4">
            <Button
              size="sm"
              variant="flat"
              className="bg-theme-elevated text-theme-muted"
              startContent={isLoadingMore ? <Spinner size="sm" /> : <ChevronDown className="w-4 h-4" />}
              isDisabled={isLoadingMore}
              onPress={() => loadVolunteers(true)}
            >
              {t('load_more', 'Load more')}
            </Button>
          </div>
        )}
      </GlassCard>
    </div>
  );
}
