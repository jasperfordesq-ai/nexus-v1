// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SOC14 — MostAppreciatedWidget
 * Drop-in leaderboard for community/group homepages.
 */

import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Avatar, Card, CardBody, CardHeader, Spinner } from '@heroui/react';
import Award from 'lucide-react/icons/award';
import { useTranslation } from 'react-i18next';
import { api } from '@/lib/api';

interface LeaderboardEntry {
  user_id: number;
  name: string | null;
  avatar_url: string | null;
  count: number;
}

interface Props {
  period?: 'last_7d' | 'last_30d' | 'last_90d' | 'all_time';
  limit?: number;
  className?: string;
}

export function MostAppreciatedWidget({ period = 'last_30d', limit = 10, className = '' }: Props) {
  const { t } = useTranslation('common');
  const [rows, setRows] = useState<LeaderboardEntry[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;
    (async () => {
      try {
        const res = await api.get<LeaderboardEntry[]>(`/v2/appreciations/most-appreciated?period=${period}&limit=${limit}`);
        if (active && res.success && Array.isArray(res.data)) {
          setRows(res.data);
        }
      } finally {
        if (active) setLoading(false);
      }
    })();
    return () => { active = false; };
  }, [period, limit]);

  return (
    <Card className={className}>
      <CardHeader className="flex items-center gap-2">
        <Award className="w-5 h-5 text-[var(--color-warning)]" />
        <h3 className="font-semibold">{t('appreciations.most_title')}</h3>
      </CardHeader>
      <CardBody className="space-y-2">
        {loading ? (
          <div className="flex justify-center py-6"><Spinner size="sm" /></div>
        ) : rows.length === 0 ? (
          <p className="text-sm text-[var(--text-muted)]">
            {t('appreciations.most_empty')}
          </p>
        ) : (
          rows.map((r, idx) => (
            <Link
              key={r.user_id}
              to={`/users/${r.user_id}/appreciations`}
              className="flex items-center gap-2 py-1 px-1 rounded hover:bg-[var(--surface-hover)]"
            >
              <span className="text-sm font-bold w-5 text-center text-[var(--text-muted)]">{idx + 1}</span>
              <Avatar src={r.avatar_url ?? undefined} name={r.name ?? ''} size="sm" />
              <span className="flex-1 truncate text-sm">{r.name ?? t('common.someone')}</span>
              <span className="text-sm text-[var(--text-muted)]">{r.count}</span>
            </Link>
          ))
        )}
      </CardBody>
    </Card>
  );
}

export default MostAppreciatedWidget;
