// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Message Context Card (MS1)
 * Shows linked context (listing, event, job) at the top of a message thread.
 * Displays title, thumbnail, type badge, and a link to the original item.
 */

import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Chip, Skeleton } from '@heroui/react';
import {
  ListChecks,
  Calendar,
  Briefcase,
  Heart,
  ExternalLink,
  LinkIcon,
} from 'lucide-react';
import { useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAssetUrl } from '@/lib/helpers';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface ContextInfo {
  type: string;
  id: number;
  title: string;
  image_url?: string | null;
  status?: string;
}

interface MessageContextCardProps {
  contextType: string;
  contextId: number;
}

const CONTEXT_CONFIG: Record<string, { icon: typeof ListChecks; label: string; color: string; basePath: string }> = {
  listing: { icon: ListChecks, label: 'Listing', color: 'primary', basePath: '/listings' },
  event: { icon: Calendar, label: 'Event', color: 'secondary', basePath: '/events' },
  job: { icon: Briefcase, label: 'Job', color: 'warning', basePath: '/jobs' },
  volunteering: { icon: Heart, label: 'Volunteering', color: 'danger', basePath: '/volunteering' },
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function MessageContextCard({ contextType, contextId }: MessageContextCardProps) {
  const { tenantPath } = useTenant();
  const [context, setContext] = useState<ContextInfo | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function loadContext() {
      setLoading(true);
      try {
        // Try to fetch context details from the appropriate API
        const config = CONTEXT_CONFIG[contextType];
        if (!config) {
          setLoading(false);
          return;
        }

        const res = await api.get(`/v2${config.basePath}/${contextId}`);
        if (res.success && res.data) {
          const data = res.data as Record<string, unknown>;
          setContext({
            type: contextType,
            id: contextId,
            title: (data.title || data.name || data.summary || 'Untitled') as string,
            image_url: (data.image_url || data.cover_image || data.thumbnail) as string | null,
            status: data.status as string | undefined,
          });
        }
      } catch (err) {
        logError('MessageContextCard.loadContext', err);
        // Still show a basic context card even if details fail
        setContext({
          type: contextType,
          id: contextId,
          title: `${contextType.charAt(0).toUpperCase() + contextType.slice(1)} #${contextId}`,
        });
      }
      setLoading(false);
    }

    loadContext();
  }, [contextType, contextId]);

  const config = CONTEXT_CONFIG[contextType];
  if (!config) return null;

  const Icon = config.icon;
  const detailPath = `${config.basePath}/${contextId}`;

  if (loading) {
    return (
      <div className="flex items-center gap-3 p-3 rounded-lg bg-theme-elevated border border-theme-default mb-3">
        <Skeleton className="w-10 h-10 rounded-lg" />
        <div className="flex-1">
          <Skeleton className="h-4 w-32 rounded mb-1" />
          <Skeleton className="h-3 w-20 rounded" />
        </div>
      </div>
    );
  }

  if (!context) return null;

  return (
    <Link to={tenantPath(detailPath)}>
      <div className="flex items-center gap-3 p-3 rounded-lg bg-theme-elevated border border-primary/20 mb-3 hover:bg-theme-hover transition-colors group">
        <div className="flex-shrink-0">
          {context.image_url ? (
            <img
              src={resolveAssetUrl(context.image_url)}
              alt={context.title}
              className="w-10 h-10 rounded-lg object-cover"
              loading="lazy"
            />
          ) : (
            <div className={`w-10 h-10 rounded-lg flex items-center justify-center bg-${config.color}/10`}>
              <Icon className={`w-5 h-5 text-${config.color}`} />
            </div>
          )}
        </div>

        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2">
            <LinkIcon className="w-3 h-3 text-theme-subtle flex-shrink-0" />
            <span className="text-xs text-theme-subtle">Regarding</span>
            <Chip size="sm" variant="flat" color={config.color as 'primary' | 'secondary' | 'warning' | 'danger'}>
              {config.label}
            </Chip>
          </div>
          <p className="font-medium text-sm text-theme-primary truncate group-hover:text-primary transition-colors">
            {context.title}
          </p>
        </div>

        <ExternalLink className="w-4 h-4 text-theme-subtle group-hover:text-primary transition-colors flex-shrink-0" />
      </div>
    </Link>
  );
}

export default MessageContextCard;
