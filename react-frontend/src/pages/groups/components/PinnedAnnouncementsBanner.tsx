// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Pinned Announcements Banner
 * Shows pinned announcements at top of group page (above tabs).
 * Non-critical — silently fails if the API is unavailable.
 */

import { useState, useEffect } from 'react';
import { Chip } from '@heroui/react';
import { Megaphone } from 'lucide-react';
import { api } from '@/lib/api';
import { useTranslation } from 'react-i18next';

interface PinnedAnnouncement {
  id: number;
  title: string;
  content: string;
  author: { name: string };
  created_at: string;
  is_pinned?: boolean;
}

interface PinnedAnnouncementsBannerProps {
  groupId: number;
}

export function PinnedAnnouncementsBanner({ groupId }: PinnedAnnouncementsBannerProps) {
  const { t } = useTranslation('groups');
  const [pinned, setPinned] = useState<PinnedAnnouncement[]>([]);
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    async function load() {
      try {
        const res = await api.get(`/v2/groups/${groupId}/announcements?pinned=1`);
        if (res.success) {
          const payload = res.data;
          const items = Array.isArray(payload)
            ? payload
            : (payload as { announcements?: PinnedAnnouncement[] })?.announcements ?? [];
          setPinned((items as PinnedAnnouncement[]).filter((a) => a.is_pinned !== false));
        }
      } catch {
        // Silently fail — banner is non-critical
      }
      setLoaded(true);
    }
    load();
  }, [groupId]);

  if (!loaded || pinned.length === 0) return null;

  return (
    <div className="space-y-2">
      {pinned.map((announcement) => (
        <div
          key={announcement.id}
          className="flex items-start gap-3 p-3 rounded-lg bg-primary/5 border border-primary/20"
        >
          <Megaphone className="w-4 h-4 text-primary flex-shrink-0 mt-0.5" />
          <div className="flex-1 min-w-0">
            <p className="text-sm font-medium text-theme-primary">{announcement.title}</p>
            <p className="text-xs text-theme-subtle mt-0.5 line-clamp-2">{announcement.content}</p>
          </div>
          <Chip size="sm" variant="flat" color="primary" className="flex-shrink-0">{t('announcements.pinned', 'Pinned')}</Chip>
        </div>
      ))}
    </div>
  );
}
